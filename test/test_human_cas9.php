<?php
session_start();

/************************************
 * CSRF TOKEN SETUP
 ************************************/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/************************************
 * CLEAR QUERY HANDLER (CSRF SAFE)
 ************************************/
if (isset($_POST['clear_query'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/************************************
 * CONFIG
 ************************************/
$db_host = "10.162.81.134";
$db_user = "abccruser";
$db_pass = "abccrpwd";
$db_name = "Chymera";

$rows_per_page = 30;
$max_rows = 1000000;
$max_csv_rows = 1000000;

/************************************
 * DB CONNECT
 ************************************/
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/************************************
 * REQUEST STATE (CSRF CHECK)
 ************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }
}

$page = max(1, (int)($_POST['page'] ?? 1));
$jump_page = $_POST['jump_page'] ?? '';
if ($jump_page !== '' && ctype_digit($jump_page)) {
    $page = max(1, (int)$jump_page);
}

$offset = ($page - 1) * $rows_per_page;
$export_csv = isset($_POST['export_csv']);

$error = "";
$result = null;
$total_rows = 0;
$execution_time = 0;
$sql = "";

/************************************
 * USER INPUT
 ************************************/
$cas_type     = $_POST['cas_type'] ?? 'both';
$chr_location = $_POST['chr_location'] ?? '';
$upstream     = $_POST['upstream'] ?? '';
$downstream   = $_POST['downstream'] ?? '';

$locus_type  = $_POST['locus_type'] ?? 'fragment';
$gene_symbol = trim($_POST['gene_symbol'] ?? '');
$exon_id     = trim($_POST['exon_id'] ?? '');

$search_mode = $_POST['search_mode'] ?? 'within';

$chromosome = '';
$chr_start  = '';
$chr_stop   = '';

// Only validate and parse chromosome location if form was submitted (Run Query clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /************************************
     * LOCUS RESOLUTION
     ************************************/
    if ($locus_type === 'fragment') {

        if (trim($chr_location) === '') {
            $error = "Chromosome location is required.";
        } elseif (!preg_match('/^(chr[\w]+):(\d+)-(\d+)$/', $chr_location, $matches)) {
            $error = "Chromosome location must be in format chr:start-stop, e.g., chr1:94533092-94533111";
        } else {
            $chromosome = $matches[1];
            $chr_start  = $matches[2];
            $chr_stop   = $matches[3];
        }

    } elseif ($locus_type === 'gene') {

        if ($gene_symbol === '') {
            $error = "Gene symbol is required.";
        } else {
            $gene_sql = "
                SELECT seqnames, MIN(start) AS start, MAX(end) AS end
                FROM human_annot
                WHERE gene_name = '".$conn->real_escape_string($gene_symbol)."'
                GROUP BY seqnames
            ";
            $gene_res = $conn->query($gene_sql);
            if (!$gene_res || $gene_res->num_rows === 0) {
                $error = "No coordinates found for gene symbol: $gene_symbol";
            } else {
                $row = $gene_res->fetch_assoc();
                $chromosome = $row['seqnames'];
                $chr_start  = $row['start'];
                $chr_stop   = $row['end'];
            }
        }

    } elseif ($locus_type === 'exon') {

        if ($exon_id === '') {
            $error = "Exon ID is required.";
        } else {
            $exon_sql = "
                WITH RECURSIVE cte AS (
                    SELECT seqnames, start, end, exon_id, strand
                    FROM human_annot
                    WHERE type = 'exon'
                      AND exon_id = '".$conn->real_escape_string($exon_id)."'
                    UNION
                    SELECT a.seqnames, a.start, a.end, a.exon_id, a.strand
                    FROM human_annot a
                    JOIN cte
                      ON a.seqnames = cte.seqnames
                     AND a.strand = cte.strand
                     AND (
                          a.start BETWEEN cte.start AND cte.end
                          OR a.end BETWEEN cte.start AND cte.end
                     )
                     AND (a.end > cte.end OR a.start < cte.start)
                    WHERE a.type = 'exon'
                )
                SELECT seqnames, MIN(start) AS start, MAX(end) AS end FROM cte
            ";
            $exon_res = $conn->query($exon_sql);
            if (!$exon_res || $exon_res->num_rows === 0) {
                $error = "No coordinates found for exon ID: $exon_id";
            } else {
                $row = $exon_res->fetch_assoc();
                $chromosome = $row['seqnames'];
                $chr_start  = $row['start'];
                $chr_stop   = $row['end'];
            }
        }
    }
}


// Add cas_type to sql query, "cas_type" is required
if ($cas_type === 'cas9') {
    $add_cas_type = "cas_type = 'cas9'";
} elseif ($cas_type === 'cas12') {
    $add_cas_type = "cas_type = 'cas12'";
} else {
    $add_cas_type = "((cas_type = 'cas9') or (cas_type = 'cas12'))";
}



// Treat empty upstream/downstream as 0
if ($search_mode === 'outside') {
    $upstream   = ctype_digit($upstream) ? (int)$upstream : 0;
    $downstream = ctype_digit($downstream) ? (int)$downstream : 0;
} else {
    // search within coordinates
    $upstream = 0;
    $downstream = 0;
}


$has_query = ($chr_start !== '' && $chr_stop !== '' && !$error);

/************************************
 * QUERY LOGIC
 ************************************/
if ($has_query) {

    $chr_start  = (int)$chr_start;
    $chr_stop   = (int)$chr_stop;

    $up_start   = $chr_start - $upstream;
    //$up_end     = $chr_start;
    //$down_start = $chr_stop;
    $down_end   = $chr_stop + $downstream;

    $start_time = microtime(true);

    /************************************
     * COUNT QUERY
     ************************************/
    // define row count query for within or flanking search
    $count_within = "
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT DISTINCT
                cas.seqnames, cas.start, cas.end
            FROM (
            -- Limit applied directly on human_cas9
                SELECT *
                FROM human_cas9
                WHERE
                   seqnames = '$chromosome'
                   AND (end BETWEEN $chr_start AND $chr_stop OR start BETWEEN $chr_start AND $chr_stop)
                LIMIT 1000000
            ) cas
            JOIN human_annot ens
                ON cas.start >= ens.start
                AND cas.end   <= ens.end
            WHERE
                ( (ens.seqnames = '$chromosome' AND ens.start <= $chr_start AND ens.end >= $chr_start)
                  OR (ens.seqnames = '$chromosome' AND ens.start <= $chr_stop  AND ens.end >= $chr_stop)
                  OR (ens.seqnames = '$chromosome' AND ens.start >= $chr_start AND ens.end <= $chr_stop)
            )
        ) t;
    ";
    $count_flank = <<<SQL
        SELECT COUNT(*) AS cnt
        FROM (
            select distinct cas.guide_sequence,
            concat(cas.seqnames, ':', guideseq_start, '-', guideseq_end),
            concat(cas.seqnames, ':', cas.start, '-', cas.end),
            cas.strand, pam_sequence,
            group_concat(distinct gene_name separator ', '),
            group_concat(distinct gene_id separator ', '),
            group_concat(distinct ens.strand separator ', '),
            group_concat(distinct ens.gene_type separator ', '),
            group_concat(distinct ens.exon_id separator ', '),
            $chr_start - cas.end, 'upstream',
            if(instr(group_concat(distinct ens.type), 'exon') > 0, 'TRUE', 'FALSE'),
            if((instr(group_concat(distinct ens.type), 'exon') = 0)
                and (instr(group_concat(distinct ens.type), 'gene') > 0), 'TRUE', 'FALSE'),
            if(instr(group_concat(distinct ens.type), 'UTR') > 0, 'TRUE', 'FALSE'),
            if(instr(group_concat(distinct ens.type), 'CDS') > 0, 'TRUE', 'FALSE'),
            if(instr(group_concat(distinct gene_name separator ', '), ', ') > 0, 'TRUE', 'FALSE'),
            h0 + h1 + h2 + h3,
            h0, h1, h2, h3, specificity, classification
        from
            (select guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                strand, pam_sequence, h0, h1, h2, h3, specificity, classification
             from human_cas9
             where ((seqnames = '$chromosome' and end between $up_start and $chr_start)
                   or (seqnames = '$chromosome' and start between $up_start and $chr_start))
            ) cas,
            (select seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
            from human_annot
            where (seqnames = '$chromosome' and start <= $chr_start and end >= $chr_start)
                  or (seqnames = '$chromosome' and start <= $up_start and end >= $up_start)
                  or (seqnames = '$chromosome' and start >= $up_start and end <= $chr_start)
            ) ens
        where cas.start >= ens.start and cas.end <= ens.end
        group by concat(cas.seqnames, ':', cas.start, '-', cas.end)

        UNION ALL

        select distinct cas.guide_sequence,
            concat(cas.seqnames, ':', guideseq_start, '-', guideseq_end),
            concat(cas.seqnames, ':', cas.start, '-', cas.end),
            cas.strand, pam_sequence,
            group_concat(distinct gene_name separator ', '),
            group_concat(distinct gene_id separator ', '),
            group_concat(distinct ens.strand separator ', '),
            group_concat(distinct ens.gene_type separator ', '),
            group_concat(distinct ens.exon_id separator ', '),
            cas.start - $chr_stop, 'downstream',
            if(instr(group_concat(distinct ens.type), 'exon') > 0, 'TRUE', 'FALSE'),
            if((instr(group_concat(distinct ens.type), 'exon') = 0)
                and (instr(group_concat(distinct ens.type), 'gene') > 0), 'TRUE', 'FALSE'),
            if(instr(group_concat(distinct ens.type), 'UTR') > 0, 'TRUE', 'FALSE'),
            if(instr(group_concat(distinct ens.type), 'CDS') > 0, 'TRUE', 'FALSE'),
            if(instr(group_concat(distinct gene_name separator ', '), ', ') > 0, 'TRUE', 'FALSE'),
            h0 + h1 + h2 + h3,
            h0, h1, h2, h3, specificity, classification
        from
            (select guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                strand, pam_sequence, h0, h1, h2, h3, specificity, classification
            from human_cas9
            where ((seqnames = '$chromosome' and end between $chr_stop and $down_end)
                  or (seqnames = '$chromosome' and start between $chr_stop and $down_end))
            ) cas,
            (select seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
            from human_annot
            where (seqnames = '$chromosome' and start <= $chr_stop and end >= $chr_stop)
                  or (seqnames = '$chromosome' and start <= $down_end and end >= $down_end)
                or (seqnames = '$chromosome' and start >= $chr_stop and end <= $down_end)
            ) ens
        where cas.start >= ens.start and cas.end <= ens.end
        group by concat(cas.seqnames, ':', cas.start, '-', cas.end)
    ) t
SQL;

    $count_sql = "";
    if (($upstream !== 0) or ($downstream !== 0)) {
        $count_sql = $count_flank;
    } else {
        $count_sql = $count_within;
    }

    $count_res = $conn->query($count_sql);
    if (!$count_res) {
        $error = $conn->error;
    } else {
        $total_rows = min((int)$count_res->fetch_assoc()['cnt'], $max_rows);
    }

    if (!$error) {

        $total_pages = max(1, ceil($total_rows / $rows_per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $rows_per_page;

        /************************************
         * MAIN QUERY
         ************************************/
	//Within frag query 
        $sql_within = <<<TEXT
        select distinct cas.guide_sequence as "Guide Sequence",
        concat(cas.seqnames, ':', guideseq_start, '-', guideseq_end) as "Guide Coordinates",
        concat(cas.seqnames, ':', cas.start, '-', cas.end) as "Cut Site",
        cas.strand as "Targeted Strand",
        pam_sequence as "PAM Sequence",
        group_concat(distinct gene_name separator ', ') as "Gene Name",
        group_concat(distinct gene_id separator ', ') as "Gene ID",
        group_concat(distinct ens.strand separator ', ') as "Gene Strand",
        group_concat(distinct ens.gene_type separator ', ') as "Target Category",
        group_concat(distinct ens.exon_id separator ', ') as "Exon ID",
        if(instr(group_concat(distinct ens.type), 'exon') > 0, "TRUE", "FALSE") as "Overlap Exon",
        if((instr(group_concat(distinct ens.type), 'exon') = 0) and (instr(group_concat(distinct ens.type), 'gene') > 0), "TRUE", "FALSE") as "Overlap Intron",
        if(instr(group_concat(distinct ens.type), 'UTR') > 0, "TRUE", "FALSE") as "Overlap UTR",
        if(instr(group_concat(distinct ens.type), 'CDS') > 0, "TRUE", "FALSE") as "Overlap CDS",
        if(instr(group_concat(distinct gene_name separator ', '), ', ') > 0, "TRUE", "FALSE") as "Overlap 2nd Gene",
        h0 + h1 + h2 + h3 as "Number Of Off-Targets",
        h0 as Hamming_0, h1 as Hamming_1, h2 as Hamming_2, h3 as Hamming_3,
        specificity, classification
        from
        (
            select guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                   strand, pam_sequence, h0, h1, h2, h3, specificity, classification
            from human_cas9
            where ((seqnames = '$chromosome' and end between $chr_start and $chr_stop)
                  or (seqnames = '$chromosome' and start between $chr_start and $chr_stop))
            LIMIT 1000000
        ) cas,
        (
            select seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
            from human_annot
            where (seqnames = '$chromosome' and start <= $chr_start and end >= $chr_start)
                  or (seqnames = '$chromosome' and start <= $chr_stop and end >= $chr_stop)
                  or (seqnames = '$chromosome' and start >= $chr_start and end <= $chr_stop)
        ) ens
        where cas.start >= ens.start and cas.end <= ens.end
        group by concat(cas.seqnames, ':', cas.start, '-', cas.end)
        order by concat(cas.seqnames, ':', cas.start, '-', cas.end)
        LIMIT $rows_per_page OFFSET $offset
TEXT;
	//Upstream and Downstream query
	$sql_flank = <<<TEXT2
        select distinct  cas.guide_sequence as "Guide Sequence", concat(cas.seqnames, ':', guideseq_start, '-', guideseq_end) as "Guide Coordinates", concat(cas.seqnames, ':', cas.start, '-', cas.end) as "Cut Site", cas.strand as "Targeted Strand", pam_sequence as "PAM Sequence",
        group_concat(distinct gene_name separator ', ') as "Gene Name", 
        group_concat(distinct gene_id separator ', ') as "Gene ID",  
        group_concat(distinct ens.strand separator ', ') as "Gene Strand", group_concat(distinct ens.gene_type separator ', ') as "Target Category", 
        group_concat(distinct ens.exon_id separator ', ') as "Exon ID", 
        $chr_start - cas.end as "Distance", "upstream" as "Flanking Direction",
        if(instr(group_concat(distinct ens.type), 'exon') > 0, "TRUE", "FALSE") as "Overlap Exon", 
        if((instr(group_concat(distinct ens.type), 'exon') = 0) and (instr(group_concat(distinct ens.type), 'gene') > 0), "TRUE", "FALSE") as "Overlap Intron", 
        if(instr(group_concat(distinct ens.type), 'UTR') > 0, "TRUE", "FALSE") as "Overlap UTR", 
        if(instr(group_concat(distinct ens.type), 'CDS') > 0, "TRUE", "FALSE") as "Overlap CDS", 
        if(instr(group_concat(distinct gene_name separator ', '), ', ') > 0, "TRUE", "FALSE") as "Overlap 2nd Gene", 
        h0 + h1 + h2 + h3 as "Number Of Off-Targets", h0 as Hamming_0, h1  as Hamming_1, h2 as Hamming_2, h3 as Hamming_3, specificity, classification
        from 
	     (select guide_sequence, guideseq_start, guideseq_end, seqnames, start, end, 
             strand, pam_sequence, h0, h1, h2, h3, specificity, classification 
	     from human_cas9
	     where ((seqnames = '$chromosome'  and end between $up_start and $chr_start)
	           or (seqnames = '$chromosome' and start between  $up_start and $chr_start))) cas,
	     (select seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
	     from human_annot
	     where (seqnames = '$chromosome' and start <= $chr_start  and end >= $chr_start) 
	           or (seqnames = '$chromosome' and start <= $up_start and end >= $up_start)
                   or (seqnames = '$chromosome'  and start >= $up_start and end <= $chr_start )) ens
        where cas.start >= ens.start and cas.end <= ens.end
        group by concat(cas.seqnames, ':', cas.start, '-', cas.end)
        UNION ALL
        select distinct cas.guide_sequence as "Guide Sequence", concat(cas.seqnames, ':', guideseq_start, '-', guideseq_end) as "Guide Coordinates", concat(cas.seqnames, ':', cas.start, '-', cas.end) as "Cut Site", cas.strand as "Targeted Strand", pam_sequence as "PAM Sequence",
        group_concat(distinct gene_name separator ', ') as "Gene Name", 
        group_concat(distinct gene_id separator ', ') as "Gene ID",  
        group_concat(distinct ens.strand separator ', ') as "Gene Strand", group_concat(distinct ens.gene_type separator ', ') as "Target Category", 
        group_concat(distinct ens.exon_id separator ', ') as "Exon ID", 
        cas.start - $chr_stop  as "Distance", "downstream" as "Flanking Direction",
        if(instr(group_concat(distinct ens.type), 'exon') > 0, "TRUE", "FALSE") as "Overlap Exon", 
        if((instr(group_concat(distinct ens.type), 'exon') = 0) and (instr(group_concat(distinct ens.type), 'gene') > 0), "TRUE", "FALSE") as "Overlap Intron", 
        if(instr(group_concat(distinct ens.type), 'UTR') > 0, "TRUE", "FALSE") as "Overlap UTR", 
        if(instr(group_concat(distinct ens.type), 'CDS') > 0, "TRUE", "FALSE") as "Overlap CDS", 
        if(instr(group_concat(distinct gene_name separator ', '), ', ') > 0, "TRUE", "FALSE") as "Overlap 2nd Gene", 
        h0 + h1 + h2 + h3 as "Number Of Off-Targets", h0 as Hamming_0, h1  as Hamming_1, h2 as Hamming_2, h3 as Hamming_3, specificity, classification
        from 
	      (select guide_sequence, guideseq_start, guideseq_end, seqnames, start, end, 
              strand, pam_sequence, h0, h1, h2, h3, specificity, classification 
	      from human_cas9 
	      where ((seqnames = '$chromosome'  and end between $chr_stop  and $down_end) 
	            or (seqnames = '$chromosome' and start between $chr_stop and $down_end))) cas,
	      (select seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
	      from human_annot
	      where (seqnames = '$chromosome' and start <= $chr_stop  and end >= $chr_stop) 
	            or (seqnames = '$chromosome' and start <= $down_end  and end >= $down_end)
                    or (seqnames = '$chromosome'  and start >= $chr_stop  and end <= $down_end)) ens
        where cas.start >= ens.start and cas.end <= ens.end
        group by concat(cas.seqnames, ':', cas.start, '-', cas.end);
TEXT2;


	// process query
	$sql = "";
	if (($upstream !== 0) or ($downstream !== 0)) {
            $sql = $sql_flank;
        } else {
            $sql = $sql_within;
        }
        /************************************
         * CSV EXPORT
         ************************************/
        if ($export_csv) {

            header("Content-Type: text/csv");
            header('Content-Disposition: attachment; filename=' . $cas_type . '_query_export.csv');
            $out = fopen("php://output", "w");

            $csv_sql = str_replace("LIMIT $rows_per_page OFFSET $offset", "LIMIT $max_csv_rows", $sql);
            $res = $conn->query($csv_sql);

            if ($res) {
                $first = $res->fetch_assoc();
                if ($first) {
                    fputcsv($out, array_keys($first));
                    fputcsv($out, array_values($first));
                }
                while ($row = $res->fetch_assoc()) {
                    fputcsv($out, array_values($row));
                }
            }
            fclose($out);
            exit;
        }

        $result = $conn->query("ANALYZE ".$sql);
        if (!$result) {
            $error = $conn->error;
        }

        $execution_time = microtime(true) - $start_time;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>CRISPR Guide Query Tool</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container-fluid mt-3">

<div class="mb-3">
    <h3>Internal tool for querying CRISPR guide RNA annotations in hg38</h3>
</div>

<!-- INPUT AREA -->
<div class="card mb-4">
    <div class="card-header fw-semibold">
        User Input Area
        <span class="text-muted small ms-2">
            (<span class="text-danger">*</span> Required fields)
        </span>
    </div>

    <div class="card-body">
        <form method="post" class="row g-3">

            <!-- CSRF TOKEN -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="col">
                <!-- <label class="form-label">Cas type</label> -->
                <label class="form-label">Cas type <span class="text-danger">*</span></label>

                <select name="cas_type" class="form-select">
                    <option value="both" <?= $cas_type === 'both' ? 'selected' : '' ?>>Both</option>
                    <option value="cas9" <?= $cas_type === 'cas9' ? 'selected' : '' ?>>Cas9</option>
                    <option value="cas12" <?= $cas_type === 'cas12' ? 'selected' : '' ?>>Cas12</option>
                </select>
            </div>

            <div class="col">
                <label class="form-label">Region type <span class="text-danger">*</span></label>
                <select name="locus_type" id="locus_type" class="form-select">
                    <option value="fragment" <?= $locus_type==='fragment'?'selected':'' ?>>Fragment</option>
                    <option value="gene" <?= $locus_type==='gene'?'selected':'' ?>>Gene</option>
                    <option value="exon" <?= $locus_type==='exon'?'selected':'' ?>>Exon</option>
                </select>
            </div>

            <div class="col" id="fragment_box">
                <label class="form-label">
                    Fragment (chr:start-stop)
                    <span class="text-danger">*</span>
                </label>
                <input type="text" name="chr_location" class="form-control"
                    value="<?= htmlspecialchars($_POST['chr_location'] ?? '') ?>">
            </div>

            <div class="col" id="gene_box">
                <label class="form-label">
                    Gene Symbol
                    <span class="text-danger">*</span>
                </label>
                <input type="text" name="gene_symbol" class="form-control"
                     value="<?= htmlspecialchars($_POST['gene_symbol'] ?? '') ?>">
            </div>

            <div class="col" id="exon_box">
                 <label class="form-label">
                     Exon ID
                     <span class="text-danger">*</span>
                 </label>
                 <input type="text" name="exon_id" class="form-control"
                     value="<?= htmlspecialchars($_POST['exon_id'] ?? '') ?>">
            </div>


            <div class="col">
                 <label class="form-label">
                     Search mode <span class="text-danger">*</span>
                 </label>
                 <select name="search_mode" id="search_mode" class="form-select">
                     <option value="within" <?= $search_mode==='within'?'selected':'' ?>>
                        Search within coordinates
                     </option>
                     <option value="outside" <?= $search_mode==='outside'?'selected':'' ?>>
                        Search outside coordinates
                     </option>
                 </select>
            </div>

            <div class="col" id="flank_box">
                 <label class="form-label">
                     Upstream
                 </label>
                 <input type="text" name="upstream" class="form-control"
                     value="<?= htmlspecialchars($_POST['upstream'] ?? '') ?>">
            </div>

            <div class="col" id="flank_box2">
                 <label class="form-label">
                     Downstream
                 </label>
                 <input type="text" name="downstream" class="form-control"
                     value="<?= htmlspecialchars($_POST['downstream'] ?? '') ?>">
            </div>


            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" id="runBtn" disabled>Run Query</button>
                <button class="btn btn-secondary" name="clear_query" value="1">Clear Query</button>
            </div>

            <input type="hidden" name="page" value="1">
        </form>
    </div>

</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!--  the following is for debug purpose only, print out the query -->
<?php if ($sql): ?>
<pre><?= htmlspecialchars($sql) ?></pre>
<?php endif; ?>

<!-- message for empty results -->
<?php if (!$error && $has_query && $result && $result->num_rows === 0): ?>
<div class="alert alert-warning">
    No results found for the specified query.
</div>
<?php endif; ?>

<?php if ($result && $result->num_rows): ?>

<div class="mb-2">
<strong>Total rows:</strong> <?= $total_rows ?> |
<strong>Execution:</strong> <?= number_format($execution_time,4) ?> s
</div>

<table class="table table-sm table-bordered">
<thead><tr>
<?php foreach ($result->fetch_fields() as $f): ?>
<th><?= htmlspecialchars($f->name) ?></th>
<?php endforeach; ?>
</tr></thead>
<tbody>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
<?php foreach ($row as $val): ?>
<td><?= htmlspecialchars((string)$val) ?></td>
<?php endforeach; ?>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<form method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

<?php
foreach ($_POST as $k=>$v) {
    if (!in_array($k,['page','export_csv','jump_page','csrf_token'])) {
        echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
    }
}
?>

<div class="d-flex align-items-center gap-3 flex-wrap">

<ul class="pagination pagination-sm mb-0">
<?php if ($page>1): ?><li class="page-item"><button class="page-link" name="page" value="<?= $page-1 ?>">Prev</button></li><?php endif; ?>
<?php for ($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
<li class="page-item <?= $p==$page?'active':'' ?>">
<button class="page-link" name="page" value="<?= $p ?>"><?= $p ?></button>
</li>
<?php endfor; ?>
<?php if ($page<$total_pages): ?><li class="page-item"><button class="page-link" name="page" value="<?= $page+1 ?>">Next</button></li><?php endif; ?>
</ul>

<button class="btn btn-success btn-sm" name="export_csv" value="1">Export CSV</button>

</div>

<div class="mt-2 d-flex align-items-center gap-3 text-muted small">
<span>Page <?= $page ?> of <?= $total_pages ?></span>
<label class="mb-0">Jump to page:</label>
<input type="number" name="jump_page" min="1" max="<?= $total_pages ?>"
       class="form-control form-control-sm" style="width:80px;">
<button class="btn btn-primary btn-sm">Go</button>
</div>

</form>

<?php endif; ?>
<script>
function toggleLocusFields() {
    const type = document.getElementById('locus_type').value;

    document.getElementById('fragment_box').style.display = (type === 'fragment') ? 'block' : 'none';
    document.getElementById('gene_box').style.display     = (type === 'gene')     ? 'block' : 'none';
    document.getElementById('exon_box').style.display     = (type === 'exon')     ? 'block' : 'none';
}

document.getElementById('locus_type').addEventListener('change', toggleLocusFields);
document.addEventListener('DOMContentLoaded', toggleLocusFields);

function toggleFlankingFields() {
    const mode = document.getElementById('search_mode').value;
    const show = (mode === 'outside');

    document.getElementById('flank_box').style.display  = show ? 'block' : 'none';
    document.getElementById('flank_box2').style.display = show ? 'block' : 'none';
}

document.getElementById('search_mode').addEventListener('change', toggleFlankingFields);
document.addEventListener('DOMContentLoaded', toggleFlankingFields);
</script>

<!-- form validation -->
<script>
function isValidFragment(value) {
    return /^(chr[\w]+):(\d+)-(\d+)$/.test(value);
}

function isPositiveInteger(value) {
    return /^\d+$/.test(value);
}

function validateForm() {
    const locusType  = document.getElementById('locus_type').value;
    const searchMode = document.getElementById('search_mode').value;

    const fragment   = document.querySelector('[name="chr_location"]').value.trim();
    const gene       = document.querySelector('[name="gene_symbol"]').value.trim();
    const exon       = document.querySelector('[name="exon_id"]').value.trim();
    const upstream   = document.querySelector('[name="upstream"]').value.trim();
    const downstream = document.querySelector('[name="downstream"]').value.trim();

    let valid = true;

    // Region validation
    if (locusType === 'fragment') {
        valid = isValidFragment(fragment);
    }
    else if (locusType === 'gene') {
        valid = gene.length > 0;
    }
    else if (locusType === 'exon') {
        valid = exon.length > 0;
    }

    // Flanking validation
    if (valid && searchMode === 'outside') {
        const upValid   = upstream === '' || isPositiveInteger(upstream);
        const downValid = downstream === '' || isPositiveInteger(downstream);

        // At least one must be > 0
        const hasValue = (isPositiveInteger(upstream) && parseInt(upstream) > 0) ||
                         (isPositiveInteger(downstream) && parseInt(downstream) > 0);

        valid = upValid && downValid && hasValue;
    }

    document.getElementById('runBtn').disabled = !valid;
}

/* Attach listeners */
document.addEventListener('DOMContentLoaded', function () {

    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(el => {
        el.addEventListener('input', validateForm);
        el.addEventListener('change', validateForm);
    });

    validateForm(); // initial state
});

</script>

</body>
</html>

