<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

include_once($_SERVER['DOCUMENT_ROOT'].'/config/db.php');

$db_conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// ── Helpers ───────────────────────────────────────────────────────────────────

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function post_string(string $key, string $default = ''): string
{
    if (!isset($_POST[$key])) return $default;
    return trim((string) $_POST[$key]);
}

function post_int(string $key, ?int $default = null): ?int
{
    if (!isset($_POST[$key]) || $_POST[$key] === '') return $default;
    return filter_var($_POST[$key], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
}

function split_multi(string $value): array
{
    $value = trim($value);
    if ($value === '') return [];
    $parts = preg_split('/[;,\n\r]+/', $value) ?: [];
    $parts = array_map('trim', $parts);
    return array_values(array_filter($parts, static fn($v) => $v !== ''));
}

function escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

// ── Column definitions ────────────────────────────────────────────────────────

function result_columns(string $label, bool $outside = false): array
{
    $cols = [
        'Cas Type',
        'Guide Sequence',
        'Guide Coordinates',
        'Cut Site',
        'Targeted Strand',
        'PAM Sequence',
        'Gene Symbol',
        'Ensembl Gene ID',
        'Gene Strand',
        'Target Category',
        'Exon ID',
    ];

    if ($outside) {
        $cols[] = 'Distance';
        $cols[] = 'Flanking Direction';
    }

    $cols = array_merge($cols, [
        'Overlap Exon',
        'Overlap Intron',
        'Overlap UTR',
        'Overlap CDS',
        'Overlap 2nd Gene',
        'Number Of Off-Targets',
        'Hamming_0',
        'Hamming_1',
        'Hamming_2',
        'Hamming_3',
        'specificity',
        'classification',
    ]);

    if ($label === 'cas12') {
        $cols = array_merge($cols, [
            'HydraNet CDS Lb.D156R',
            'HydraNet CDS Lb.WT',
            'HydraNet CDS As.op',
            'HydraNet Seq Lb.D156R',
            'HydraNet Seq Lb.WT',
            'HydraNet Seq As.op',
        ]);
    } else {
        $cols = array_merge($cols, [
            'RS3 Seq',
            'RS3 Target',
            'RS3 Seq Target',
        ]);
    }

    return $cols;
}

// ── Score column fragments ────────────────────────────────────────────────────

function score_cols_select(string $label): string
{
    return $label === 'cas12'
        ? ",
           cas.HydraNet_CDS_Lb_D156R_Cas12a      AS `HydraNet CDS Lb.D156R`,
           cas.HydraNet_CDS_Lb_WT_Cas12a         AS `HydraNet CDS Lb.WT`,
           cas.HydraNet_CDS_As_opCas12a          AS `HydraNet CDS As.op`,
           cas.HydraNet_Seq_Lb_D156R_Cas12a      AS `HydraNet Seq Lb.D156R`,
           cas.HydraNet_Seq_Lb_WT_Cas12a         AS `HydraNet Seq Lb.WT`,
           cas.HydraNet_Seq_As_opCas12a          AS `HydraNet Seq As.op`"
        : ",
           cas.RS3_seq                           AS `RS3 Seq`,
           cas.RS3_target                        AS `RS3 Target`,
           cas.RS3_seq_target                    AS `RS3 Seq Target`";
}

function score_cols_inner(string $label): string
{
    return $label === 'cas12'
        ? ", HydraNet_CDS_Lb_D156R_Cas12a, HydraNet_CDS_Lb_WT_Cas12a, HydraNet_CDS_As_opCas12a,
               HydraNet_Seq_Lb_D156R_Cas12a, HydraNet_Seq_Lb_WT_Cas12a, HydraNet_Seq_As_opCas12a"
        : ", RS3_seq, RS3_target, RS3_seq_target";
}

// ── Sort expression ───────────────────────────────────────────────────────────

function sort_expr(string $sortCol, string $sortDir, string $prefix = 'cas'): string
{
    $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
    $expr = match ($sortCol) {
        'Guide Sequence'        => "$prefix.guide_sequence",
        'Guide Coordinates'     => "CONCAT($prefix.seqnames, ':', guideseq_start, '-', guideseq_end)",
        'Targeted Strand'       => "$prefix.strand",
        'PAM Sequence'          => "pam_sequence",
        'Number Of Off-Targets' => "h0 + h1 + h2 + h3",
        'Hamming_0'             => "h0",
        'Hamming_1'             => "h1",
        'Hamming_2'             => "h2",
        'Hamming_3'             => "h3",
        'specificity'           => "specificity",
        'classification'        => "classification",
        'Distance'              => "Distance",
        'Flanking Direction'    => "`Flanking Direction`",
        default                 => "`Cut Site`",
    };
    return "$expr $dir";
}

// ── Search clause ─────────────────────────────────────────────────────────────

function build_search_clause(string $label, string $searchTerm, bool $outside = false): string
{
    global $db_conn;
    $searchTerm = trim($searchTerm);
    if ($searchTerm === '') return '';

    $cols  = result_columns($label, $outside);
    $parts = array_map(static function (string $col): string {
        $safe = str_replace('`', '``', $col);
        return "COALESCE(CAST(base.`$safe` AS CHAR), '')";
    }, $cols);

    $needle = escape_like(mb_strtolower($searchTerm));
    $needle = mysqli_real_escape_string($db_conn, $needle);
    return "LOWER(CONCAT_WS(' ', " . implode(', ', $parts) . ")) LIKE '%$needle%' ESCAPE '\\\\'";
}

// ── Order clause (outer / wrapper queries) ────────────────────────────────────

function build_order_clause(string $label, string $sortCol, string $sortDir, bool $outside = false): string
{
    $allowed = [];
    foreach (result_columns($label, $outside) as $col) {
        $safe          = str_replace('`', '``', $col);
        $allowed[$col] = "base.`$safe`";
    }
    $sortCol = isset($allowed[$sortCol]) ? $sortCol : 'Cut Site';
    $dir     = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
    return $allowed[$sortCol] . ' ' . $dir;
}

// ── Core SELECT for one coordinate range (no LIMIT, sort baked in) ────────────

function build_result_query(
    string $table,
    string $label,
    string $var_chr,
    int    $var_start,
    int    $var_end,
    string $sortCol = 'Cut Site',
    string $sortDir = 'ASC'
): string {
    $guide_where = "seqnames = '$var_chr' AND start <= $var_end AND end >= $var_start";
    $annot_where = "seqnames = '$var_chr' AND start <= $var_end AND end >= $var_start";

    $sc = score_cols_select($label);
    $si = score_cols_inner($label);
    $se = sort_expr($sortCol, $sortDir);

    return "
        SELECT DISTINCT
            '$label'              AS `Cas Type`,
            cas.guide_sequence    AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand            AS `Targeted Strand`,
            pam_sequence          AS `PAM Sequence`,
            GROUP_CONCAT(DISTINCT gene_name     SEPARATOR ', ') AS `Gene Symbol`,
            GROUP_CONCAT(DISTINCT gene_id       SEPARATOR ', ') AS `Ensembl Gene ID`,
            GROUP_CONCAT(DISTINCT ens.strand    SEPARATOR ', ') AS `Gene Strand`,
            GROUP_CONCAT(DISTINCT ens.gene_type SEPARATOR ', ') AS `Target Category`,
            GROUP_CONCAT(DISTINCT ens.exon_id   SEPARATOR ', ') AS `Exon ID`,
            GROUP_CONCAT(DISTINCT ens.transcript_id   SEPARATOR ', ') AS `Transcript ID`,
            GROUP_CONCAT(DISTINCT ens.exon_number   SEPARATOR ', ') AS `Exon Number`,
            CONCAT(count(distinct ens.transcript_id),'/',ens.transcript_count, ' (', round(count(distinct ens.transcript_id) / ens.transcript_count * 100), '%) ' ) AS `Targeted Transcripts`, 
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') > 0, 'TRUE', 'FALSE')                                                               AS `Overlap Exon`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') = 0 AND INSTR(GROUP_CONCAT(DISTINCT ens.type), 'gene') > 0, 'TRUE', 'FALSE')        AS `Overlap Intron`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'UTR')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap UTR`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'CDS')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap CDS`,
            IF(INSTR(GROUP_CONCAT(DISTINCT gene_name SEPARATOR ', '), ', ') > 0, 'TRUE', 'FALSE')                                                  AS `Overlap 2nd Gene`,
            h0 + h1 + h2 + h3 - 1     AS `Number Of Off-Targets`,
            h0 AS Hamming_0, h1 AS Hamming_1, h2 AS Hamming_2, h3 AS Hamming_3,
            specificity, classification
            $sc
        FROM (
            SELECT guide_sequence, guideseq_start, guideseq_end,
                   seqnames, start, end, strand, pam_sequence,
                   h0, h1, h2, h3, specificity, classification
                   $si
            FROM $table
            WHERE $guide_where
            LIMIT 1000000
        ) cas JOIN
        (
        SELECT seqnames, start, end, strand, type, gene_id, gene_type, 
                gene_name, exon_id, transcript_id, exon_number, transcript_count
            FROM human_annot
            WHERE $annot_where and gene_type = 'protein_coding'
        ) ens
        ON cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY cas.seqnames, cas.start, cas.end, cas.strand, ens.gene_name
        ORDER BY $se
    ";
}

// ── Count query for a single coordinate range ─────────────────────────────────

function build_count_query(
    string $table,
    string $var_chr,
    int    $var_start,
    int    $var_end
): string {
    $guide_where = "seqnames = '$var_chr' AND start <= $var_end AND end >= $var_start";
    $annot_where = "seqnames = '$var_chr' AND start <= $var_end AND end >= $var_start";

    return "
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT DISTINCT cas.seqnames, cas.start, cas.end, cas.strand
            FROM (
                SELECT seqnames, start, end, strand
                FROM $table
                WHERE $guide_where
                LIMIT 1000000
            ) cas
            JOIN (
                SELECT DISTINCT seqnames, start, end
                FROM human_annot
                WHERE $annot_where
            ) ens
            ON cas.start >= ens.start AND cas.end <= ens.end
        ) t
    ";
}

// ── Filtered + paginated wrapper (deletion-fragments / single range) ──────────

function build_filtered_query(
    string $table,
    string $label,
    string $var_chr,
    int    $var_start,
    int    $var_end,
    int    $limit,
    int    $offset,
    string $searchTerm,
    string $sortCol,
    string $sortDir
): string {
    if (trim($searchTerm) === '') {
        $base = build_result_query($table, $label, $var_chr, $var_start, $var_end, $sortCol, $sortDir);
        $sql  = $base;
        if ($limit > 0) $sql .= " LIMIT $offset, $limit";
        return $sql;
    }

    $base  = build_result_query($table, $label, $var_chr, $var_start, $var_end);
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);
    $sql   = "SELECT base.* FROM ($base) base WHERE $where ORDER BY $order";
    if ($limit > 0) $sql .= " LIMIT $offset, $limit";
    return $sql;
}

// ── Outside (flanking) query ──────────────────────────────────────────────────

function build_outside_query(
    string $table,
    string $label,
    string $var_chr,
    int    $var_start,
    int    $var_end,
    int    $flank_limit,
    int    $rows_limit,
    int    $offset,
    string $searchTerm,
    string $sortCol,
    string $sortDir,
    int    $total_cap = 10000
): string {
    $up_start  = $var_start - $flank_limit;
    $down_end  = $var_end   + $flank_limit;
    $outside   = true;

    $sc = score_cols_select($label);
    $si = score_cols_inner($label);

    $shared_select = "
            '$label'              AS `Cas Type`,
            cas.guide_sequence    AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand            AS `Targeted Strand`,
            pam_sequence          AS `PAM Sequence`,
            GROUP_CONCAT(DISTINCT gene_name     SEPARATOR ', ') AS `Gene Symbol`,
            GROUP_CONCAT(DISTINCT gene_id       SEPARATOR ', ') AS `Ensembl Gene ID`,
            GROUP_CONCAT(DISTINCT ens.strand    SEPARATOR ', ') AS `Gene Strand`,
            GROUP_CONCAT(DISTINCT ens.gene_type SEPARATOR ', ') AS `Target Category`,
            GROUP_CONCAT(DISTINCT ens.exon_id   SEPARATOR ', ') AS `Exon ID`";

    $shared_tail = "
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') > 0, 'TRUE', 'FALSE')                                                               AS `Overlap Exon`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') = 0 AND INSTR(GROUP_CONCAT(DISTINCT ens.type), 'gene') > 0, 'TRUE', 'FALSE')        AS `Overlap Intron`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'UTR')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap UTR`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'CDS')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap CDS`,
            IF(INSTR(GROUP_CONCAT(DISTINCT gene_name SEPARATOR ', '), ', ') > 0, 'TRUE', 'FALSE')                                                  AS `Overlap 2nd Gene`,
            h0 + h1 + h2 + h3     AS `Number Of Off-Targets`,
            h0 AS Hamming_0, h1 AS Hamming_1, h2 AS Hamming_2, h3 AS Hamming_3,
            specificity, classification
            $sc";

    $up_guide   = "seqnames = '$var_chr'
        AND (end BETWEEN $up_start AND $var_start OR start BETWEEN $up_start AND $var_start)";
    $up_annot   = "(seqnames = '$var_chr' AND start <= $var_start AND end >= $var_start)
        OR (seqnames = '$var_chr' AND start <= $up_start AND end >= $up_start)
        OR (seqnames = '$var_chr' AND start >= $up_start AND end <= $var_start)";
    $down_guide = "seqnames = '$var_chr'
        AND (end BETWEEN $var_end AND $down_end OR start BETWEEN $var_end AND $down_end)";
    $down_annot = "(seqnames = '$var_chr' AND start <= $var_end  AND end >= $var_end)
        OR (seqnames = '$var_chr' AND start <= $down_end AND end >= $down_end)
        OR (seqnames = '$var_chr' AND start >= $var_end  AND end <= $down_end)";

    $up_branch = "
        SELECT $shared_select,
            $var_start - cas.end AS `Distance`,
            'upstream'           AS `Flanking Direction`,
            $shared_tail
        FROM (SELECT guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                     strand, pam_sequence, h0, h1, h2, h3, specificity, classification $si
              FROM $table WHERE $up_guide LIMIT 1000000) cas,
             (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
              FROM human_annot WHERE $up_annot) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";

    $down_branch = "
        SELECT $shared_select,
            cas.start - $var_end AS `Distance`,
            'downstream'         AS `Flanking Direction`,
            $shared_tail
        FROM (SELECT guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                     strand, pam_sequence, h0, h1, h2, h3, specificity, classification $si
              FROM $table WHERE $down_guide LIMIT 1000000) cas,
             (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
              FROM human_annot WHERE $down_annot) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";

    $union = "($up_branch) UNION ALL ($down_branch)";

    $where = build_search_clause($label, $searchTerm, $outside);
    $order = build_order_clause($label, $sortCol, $sortDir, $outside);

    $sql = "SELECT base.* FROM ($union LIMIT $total_cap) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql .= " ORDER BY $order";
    if ($rows_limit > 0) $sql .= " LIMIT $offset, $rows_limit";
    return $sql;
}

function build_outside_count_query(
    string $table,
    string $label,
    string $var_chr,
    int    $var_start,
    int    $var_end,
    int    $flank_limit,
    string $searchTerm,
    int    $total_cap = 10000
): string {
    $up_start  = $var_start - $flank_limit;
    $down_end  = $var_end   + $flank_limit;
    $outside   = true;

    $sc = score_cols_select($label);
    $si = score_cols_inner($label);

    $shared_select = "
            '$label'              AS `Cas Type`,
            cas.guide_sequence    AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand            AS `Targeted Strand`,
            pam_sequence          AS `PAM Sequence`,
            GROUP_CONCAT(DISTINCT gene_name     SEPARATOR ', ') AS `Gene Symbol`,
            GROUP_CONCAT(DISTINCT gene_id       SEPARATOR ', ') AS `Ensembl Gene ID`,
            GROUP_CONCAT(DISTINCT ens.strand    SEPARATOR ', ') AS `Gene Strand`,
            GROUP_CONCAT(DISTINCT ens.gene_type SEPARATOR ', ') AS `Target Category`,
            GROUP_CONCAT(DISTINCT ens.exon_id   SEPARATOR ', ') AS `Exon ID`";

    $shared_tail = "
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') > 0, 'TRUE', 'FALSE')                                                               AS `Overlap Exon`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') = 0 AND INSTR(GROUP_CONCAT(DISTINCT ens.type), 'gene') > 0, 'TRUE', 'FALSE')        AS `Overlap Intron`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'UTR')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap UTR`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'CDS')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap CDS`,
            IF(INSTR(GROUP_CONCAT(DISTINCT gene_name SEPARATOR ', '), ', ') > 0, 'TRUE', 'FALSE')                                                  AS `Overlap 2nd Gene`,
            h0 + h1 + h2 + h3     AS `Number Of Off-Targets`,
            h0 AS Hamming_0, h1 AS Hamming_1, h2 AS Hamming_2, h3 AS Hamming_3,
            specificity, classification
            $sc";

    $up_guide   = "seqnames = '$var_chr'
        AND (end BETWEEN $up_start AND $var_start OR start BETWEEN $up_start AND $var_start)";
    $up_annot   = "(seqnames = '$var_chr' AND start <= $var_start AND end >= $var_start)
        OR (seqnames = '$var_chr' AND start <= $up_start AND end >= $up_start)
        OR (seqnames = '$var_chr' AND start >= $up_start AND end <= $var_start)";
    $down_guide = "seqnames = '$var_chr'
        AND (end BETWEEN $var_end AND $down_end OR start BETWEEN $var_end AND $down_end)";
    $down_annot = "(seqnames = '$var_chr' AND start <= $var_end  AND end >= $var_end)
        OR (seqnames = '$var_chr' AND start <= $down_end AND end >= $down_end)
        OR (seqnames = '$var_chr' AND start >= $var_end  AND end <= $down_end)";

    $up_branch = "
        SELECT $shared_select,
            $var_start - cas.end AS `Distance`,
            'upstream'           AS `Flanking Direction`,
            $shared_tail
        FROM (SELECT guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                     strand, pam_sequence, h0, h1, h2, h3, specificity, classification $si
              FROM $table WHERE $up_guide LIMIT 1000000) cas,
             (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
              FROM human_annot WHERE $up_annot) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";

    $down_branch = "
        SELECT $shared_select,
            cas.start - $var_end AS `Distance`,
            'downstream'         AS `Flanking Direction`,
            $shared_tail
        FROM (SELECT guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                     strand, pam_sequence, h0, h1, h2, h3, specificity, classification $si
              FROM $table WHERE $down_guide LIMIT 1000000) cas,
             (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
              FROM human_annot WHERE $down_annot) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";

    $union = "($up_branch) UNION ALL ($down_branch)";
    $where = build_search_clause($label, $searchTerm, $outside);

    $sql = "SELECT COUNT(*) AS cnt FROM ($union LIMIT $total_cap) base";
    if ($where !== '') $sql .= " WHERE $where";
    return $sql;
}

// ── Knockout: fetch CDS rows ──────────────────────────────────────────────────

function knockout_cds_rows(array $gene_symbols, array $ensembl_ids): array
{
    global $db_conn;
    $conditions = [];
    if ($gene_symbols) {
        $esc          = array_map(static fn($v) => "'" . mysqli_real_escape_string($db_conn, $v) . "'", $gene_symbols);
        $conditions[] = 'gene_name IN (' . implode(',', $esc) . ')';
    }
    if ($ensembl_ids) {
        $esc          = array_map(static fn($v) => "'" . mysqli_real_escape_string($db_conn, $v) . "'", $ensembl_ids);
        $conditions[] = 'gene_id IN (' . implode(',', $esc) . ')';
        $conditions[] = 'substr(gene_id, 1, instr(gene_id, ".") - 1) IN (' . implode(',', $esc) . ')';
    }
    if (!$conditions) return [];
    $where  = '(' . implode(' OR ', $conditions) . ") AND type = 'CDS'";
    $sql    = "SELECT DISTINCT seqnames, start, end FROM human_annot WHERE $where ORDER BY seqnames, start";
    $result = $db_conn->query($sql);
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = ['chr' => $row['seqnames'], 'start' => (int)$row['start'], 'end' => (int)$row['end']];
    }
    return $rows;
}

// ── Knockout: single-chromosome query (replaces per-CDS-row fan-out) ──────────
//
// Instead of calling build_result_query() once per CDS row and UNIONing ~90
// branches (each doing an independent full scan + join), we group all CDS rows
// by chromosome, compute the bounding box to exploit the index, and then
// filter with a compact OR list so only guides that truly overlap a CDS row
// are returned. For BRCA1 this reduces ~90 UNION branches to 1.

function cds_ranges_where(array $rows): string
{
    $parts = array_map(
        static fn($r) => "(start <= {$r['end']} AND end >= {$r['start']})",
        $rows
    );
    return '(' . implode(' OR ', $parts) . ')';
}

function build_knockout_chr_query(
    string $table,
    string $label,
    string $chr,
    array  $chr_rows,
    string $sortCol = 'Cut Site',
    string $sortDir = 'ASC'
): string {
    $min_start = min(array_column($chr_rows, 'start'));
    $max_end   = max(array_column($chr_rows, 'end'));

    // Bounding box hits the index; OR list filters to exact CDS ranges.
    $guide_where = "seqnames = '$chr' AND start <= $max_end AND end >= $min_start"
                 . ' AND ' . cds_ranges_where($chr_rows);
    $annot_where = "seqnames = '$chr' AND start <= $max_end AND end >= $min_start";

    $sc = score_cols_select($label);
    $si = score_cols_inner($label);
    $se = sort_expr($sortCol, $sortDir);

    return "
        SELECT DISTINCT
            '$label'              AS `Cas Type`,
            cas.guide_sequence    AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand            AS `Targeted Strand`,
            pam_sequence          AS `PAM Sequence`,
            GROUP_CONCAT(DISTINCT gene_name     SEPARATOR ', ') AS `Gene Symbol`,
            GROUP_CONCAT(DISTINCT gene_id       SEPARATOR ', ') AS `Ensembl Gene ID`,
            GROUP_CONCAT(DISTINCT ens.strand    SEPARATOR ', ') AS `Gene Strand`,
            GROUP_CONCAT(DISTINCT ens.gene_type SEPARATOR ', ') AS `Target Category`,
            GROUP_CONCAT(DISTINCT ens.exon_id   SEPARATOR ', ') AS `Exon ID`,
            GROUP_CONCAT(DISTINCT ens.transcript_id   SEPARATOR ', ') AS `Transcript ID`,
            GROUP_CONCAT(DISTINCT ens.exon_number     SEPARATOR ', ') AS `Exon Number`,
            CONCAT(count(distinct ens.transcript_id),'/',ens.transcript_count, ' (',
                   round(count(distinct ens.transcript_id) / ens.transcript_count * 100),
                   '%) ' )                                              AS `Targeted Transcripts`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') > 0, 'TRUE', 'FALSE')                                                               AS `Overlap Exon`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') = 0 AND INSTR(GROUP_CONCAT(DISTINCT ens.type), 'gene') > 0, 'TRUE', 'FALSE')        AS `Overlap Intron`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'UTR')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap UTR`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'CDS')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap CDS`,
            IF(INSTR(GROUP_CONCAT(DISTINCT gene_name SEPARATOR ', '), ', ') > 0, 'TRUE', 'FALSE')                                                  AS `Overlap 2nd Gene`,
            h0 + h1 + h2 + h3 - 1     AS `Number Of Off-Targets`,
            h0 AS Hamming_0, h1 AS Hamming_1, h2 AS Hamming_2, h3 AS Hamming_3,
            specificity, classification
            $sc
        FROM (
            SELECT guide_sequence, guideseq_start, guideseq_end,
                   seqnames, start, end, strand, pam_sequence,
                   h0, h1, h2, h3, specificity, classification
                   $si
            FROM $table
            WHERE $guide_where
            LIMIT 1000000
        ) cas
        JOIN (
            SELECT seqnames, start, end, strand, type, gene_id, gene_type,
                   gene_name, exon_id, transcript_id, exon_number, transcript_count
            FROM human_annot
            WHERE $annot_where and gene_type = 'protein_coding'
        ) ens ON cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY cas.seqnames, cas.start, cas.end, cas.strand
        ORDER BY $se
    ";
}

// ── Knockout: UNION query across CDS rows ─────────────────────────────────────

function build_knockout_union_query(
    string $table,
    string $label,
    array  $cds_rows,
    int    $rows_limit,
    int    $offset,
    string $searchTerm,
    string $sortCol,
    string $sortDir,
    int    $total_cap = 1000000
): string {
    // Group by chromosome → one branch per chr instead of one per CDS row
    $by_chr = [];
    foreach ($cds_rows as $r) {
        $by_chr[$r['chr']][] = $r;
    }

    $branches = array_map(
        static fn($chr, $rows) => '(' . build_knockout_chr_query($table, $label, $chr, $rows) . ')',
        array_keys($by_chr),
        array_values($by_chr)
    );

    $union = 'SELECT DISTINCT * FROM (' . implode(' UNION ALL ', $branches) . ') _union LIMIT ' . $total_cap;

    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);

    $sql = "SELECT base.* FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql .= " ORDER BY $order";
    if ($rows_limit > 0) $sql .= " LIMIT $offset, $rows_limit";
    return $sql;
}

function build_knockout_count_query(
    string $table,
    string $label,
    array  $cds_rows,
    string $searchTerm,
    int    $total_cap = 1000000
): string {
    $by_chr = [];
    foreach ($cds_rows as $r) {
        $by_chr[$r['chr']][] = $r;
    }

    $branches = array_map(
        static fn($chr, $rows) => '(' . build_knockout_chr_query($table, $label, $chr, $rows) . ')',
        array_keys($by_chr),
        array_values($by_chr)
    );

    $union = 'SELECT DISTINCT * FROM (' . implode(' UNION ALL ', $branches) . ') _union LIMIT ' . $total_cap;
    $where = build_search_clause($label, $searchTerm);

    $sql = "SELECT COUNT(*) AS cnt FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    return $sql;
}

// ── Deletion-of-exons: resolve bounding ranges ────────────────────────────────

function deletion_exon_ranges(string $exon_choice, array $exon_inputs): array
{
    global $db_conn;
    $ranges = [];

    if ($exon_choice === 'ensembl-exon') {
        foreach ($exon_inputs as $exon_id) {
            $safe = mysqli_real_escape_string($db_conn, $exon_id);
            $sql  = "
                WITH RECURSIVE cte AS (
                    SELECT seqnames, start, end, exon_id, type, strand
                    FROM human_annot
                    WHERE type = 'exon' AND 
                    (exon_id = '$safe' OR substr(exon_id, 1,instr(exon_id,'.')-1) = '$safe')
                    UNION
                    SELECT a.seqnames, a.start, a.end, a.exon_id, a.type, a.strand
                    FROM (SELECT seqnames, start, end, exon_id, type, strand
                          FROM human_annot WHERE type = 'exon') a
                    JOIN cte ON a.seqnames = cte.seqnames
                    WHERE ((a.start BETWEEN cte.start AND cte.end)
                        OR (a.end   BETWEEN cte.start AND cte.end))
                      AND a.strand = cte.strand
                      AND (a.end > cte.end OR a.start < cte.start)
                )
                SELECT seqnames,
                       MIN(start) AS start,
                       MAX(end) AS end
                FROM cte";
            $result = $db_conn->query($sql);
            if (!$result) continue;
            $row = $result->fetch_assoc();
            if ($row && $row['seqnames'] !== null) {
                $ranges[] = [
                    'chr'   => $row['seqnames'],
                    'start' => (int)$row['start'],
                    'end'   => (int)$row['end'],
                ];
            }
        }
    } elseif ($exon_choice === 'exon-coordinates') {
        foreach ($exon_inputs as $coord) {
            $colon = stripos($coord, ':');
            $dash  = stripos($coord, '-');
            if ($colon === false || $dash === false || $dash <= $colon) continue;
            $chr   = preg_replace('/[^a-zA-Z0-9_]/', '', substr($coord, 0, $colon));
            $start = (int)substr($coord, $colon + 1, $dash - $colon - 1);
            $end   = (int)substr($coord, $dash + 1);
            $ranges[] = ['chr' => $chr, 'start' => $start, 'end' => $end];
        }
    }

    return $ranges;
}

// ── Deletion-of-exons: per-range exon filter ─────────────────────────────────

function apply_exon_filter(array $rows, array $col_map): array
{
    $idx_range     = $col_map['_exon_range']      ?? null;
    $idx_direction = $col_map['Flanking Direction'] ?? null;
    $idx_distance  = $col_map['Distance']           ?? null;
    $idx_overlap   = $col_map['Overlap Exon']       ?? null;

    if ($idx_range === null || $idx_direction === null || $idx_distance === null || $idx_overlap === null) {
        if ($idx_range !== null) {
            foreach ($rows as &$row) { array_splice($row, $idx_range, 1); }
            unset($row);
        }
        return $rows;
    }

    $groups = [];
    foreach ($rows as $row) {
        $key = ($row[$idx_range] ?? '') . '|' . ($row[$idx_direction] ?? '');
        $groups[$key][] = $row;
    }

    $filtered = [];
    foreach ($groups as $rows_in_group) {
        usort($rows_in_group, static function ($a, $b) use ($idx_distance) {
            return (float)($a[$idx_distance] ?? 0) <=> (float)($b[$idx_distance] ?? 0);
        });

        $boundary = null;
        foreach ($rows_in_group as $row) {
            if (($row[$idx_overlap] ?? '') === 'TRUE') {
                $boundary = (float)($row[$idx_distance] ?? 0);
                break;
            }
        }

        foreach ($rows_in_group as $row) {
            if ($boundary === null || (float)($row[$idx_distance] ?? 0) < $boundary) {
                $filtered[] = $row;
            }
        }
    }

    foreach ($filtered as &$row) {
        array_splice($row, $idx_range, 1);
    }
    unset($row);

    return $filtered;
}

// ── Deletion-of-exons: flanking UNION query ───────────────────────────────────

function build_exon_union_query(
    string $table,
    string $label,
    array  $ranges,
    int    $max_distance,
    int    $total_cap = 10000
): string {
    $all_branches = [];

    foreach ($ranges as $r) {
        $chr        = $r['chr'];
        $start      = $r['start'];
        $end        = $r['end'];
        $up_start   = $start - $max_distance;
        $down_end   = $end   + $max_distance;
        $range_tag  = mysqli_real_escape_string($GLOBALS['db_conn'], "$chr:$start-$end");

        $sc = score_cols_select($label);
        $si = score_cols_inner($label);

        $shared_select = "
                '$label'              AS `Cas Type`,
                cas.guide_sequence    AS `Guide Sequence`,
                CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
                CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
                cas.strand            AS `Targeted Strand`,
                pam_sequence          AS `PAM Sequence`,
                GROUP_CONCAT(DISTINCT gene_name     SEPARATOR ', ') AS `Gene Symbol`,
                GROUP_CONCAT(DISTINCT gene_id       SEPARATOR ', ') AS `Ensembl Gene ID`,
                GROUP_CONCAT(DISTINCT ens.strand    SEPARATOR ', ') AS `Gene Strand`,
                GROUP_CONCAT(DISTINCT ens.gene_type SEPARATOR ', ') AS `Target Category`,
                GROUP_CONCAT(DISTINCT ens.exon_id   SEPARATOR ', ') AS `Exon ID`";

        $shared_tail = "
                IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') > 0, 'TRUE', 'FALSE')                                                               AS `Overlap Exon`,
                IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') = 0 AND INSTR(GROUP_CONCAT(DISTINCT ens.type), 'gene') > 0, 'TRUE', 'FALSE')        AS `Overlap Intron`,
                IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'UTR')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap UTR`,
                IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'CDS')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap CDS`,
                IF(INSTR(GROUP_CONCAT(DISTINCT gene_name SEPARATOR ', '), ', ') > 0, 'TRUE', 'FALSE')                                                  AS `Overlap 2nd Gene`,
                h0 + h1 + h2 + h3     AS `Number Of Off-Targets`,
                h0 AS Hamming_0, h1 AS Hamming_1, h2 AS Hamming_2, h3 AS Hamming_3,
                specificity, classification
                $sc";

        $up_guide   = "seqnames = '$chr' AND (end BETWEEN $up_start AND $start OR start BETWEEN $up_start AND $start)";
        $up_annot   = "(seqnames = '$chr' AND start <= $start AND end >= $start)
                    OR (seqnames = '$chr' AND start <= $up_start AND end >= $up_start)
                    OR (seqnames = '$chr' AND start >= $up_start AND end <= $start)";
        $down_guide = "seqnames = '$chr' AND (end BETWEEN $end AND $down_end OR start BETWEEN $end AND $down_end)";
        $down_annot = "(seqnames = '$chr' AND start <= $end AND end >= $end)
                    OR (seqnames = '$chr' AND start <= $down_end AND end >= $down_end)
                    OR (seqnames = '$chr' AND start >= $end AND end <= $down_end)";

        $all_branches[] = "
            SELECT $shared_select,
                $start - cas.end AS `Distance`,
                'upstream'       AS `Flanking Direction`,
                $shared_tail,
                '$range_tag'     AS `_exon_range`
            FROM (SELECT guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                         strand, pam_sequence, h0, h1, h2, h3, specificity, classification $si
                  FROM $table WHERE $up_guide LIMIT 1000000) cas,
                 (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
                  FROM human_annot WHERE $up_annot) ens
            WHERE cas.start >= ens.start AND cas.end <= ens.end
            GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";

        $all_branches[] = "
            SELECT $shared_select,
                cas.start - $end AS `Distance`,
                'downstream'     AS `Flanking Direction`,
                $shared_tail,
                '$range_tag'     AS `_exon_range`
            FROM (SELECT guide_sequence, guideseq_start, guideseq_end, seqnames, start, end,
                         strand, pam_sequence, h0, h1, h2, h3, specificity, classification $si
                  FROM $table WHERE $down_guide LIMIT 1000000) cas,
                 (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
                  FROM human_annot WHERE $down_annot) ens
            WHERE cas.start >= ens.start AND cas.end <= ens.end
            GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";
    }

    $union = implode(' UNION ALL ', array_map(fn($b) => "($b)", $all_branches));
    return "($union) LIMIT $total_cap";
}

// ── CRISPRi/a: resolve TSS coordinate ranges ──────────────────────────────────

/**
 * Queries human_tss_ensemble + human_tss_gene_ranking to get one genomic
 * range per primary TSS matching the supplied identifiers.
 *
 * Inputs are searched as a unified OR across gene_symbol, ensembl_gene_id,
 * and tss columns.
 *
 * @param array  $inputs     Merged list of any gene symbols / Ensembl IDs / TSS coords.
 * @param int    $upstream   Upstream offset (may be negative, e.g. -100).
 * @param int    $downstream Downstream offset.
 * @return array [ ['chr'=>..., 'start'=>..., 'end'=>...], ... ]
 */
function crispri_tss_ranges(array $inputs, int $upstream, int $downstream): array
{
    global $db_conn;
    if (empty($inputs)) return [];

    $esc_vals = array_map(
        static fn($v) => "'" . mysqli_real_escape_string($db_conn, $v) . "'",
        $inputs
    );
    $in_list = implode(',', $esc_vals);

    // Strip version suffixes from inputs in PHP
    $stripped_vals = array_map(
        static fn($v) => "'" . mysqli_real_escape_string($db_conn, preg_replace('/\.\d+$/', '', $v)) . "'",
        $inputs
    );
    $stripped_list = implode(',', $stripped_vals);

    // Placeholders that match the query supplied by the user.
    // startOffset / endOffset flip based on strand so that start < end always.
    $sql = "
        SELECT DISTINCT
            seqnames,
            pos + startOffset AS start,
            pos + endOffset   AS end
        FROM (
            SELECT
                a.strand,
                SUBSTR(b.tss, 1, INSTR(b.tss, ':') - 1)                            AS seqnames,
                CAST(SUBSTR(b.tss, INSTR(b.tss, ':') + 1, LENGTH(b.tss)) AS SIGNED) AS pos,
                IF(a.strand = '+', $upstream,   $downstream) AS startOffset,
                IF(a.strand = '+', $downstream, $upstream)   AS endOffset
            FROM human_tss_ensemble a
            JOIN human_tss_gene_ranking b ON a.id = b.id
            WHERE b.tss_tag = 'primary'
            AND (
                a.gene_symbol     IN ($in_list)
            OR a.ensembl_gene_id IN ($stripped_list)
            OR substr(a.ensembl_gene_id, 1, instr(a.ensembl_gene_id, '.') - 1) IN ($stripped_list)
            OR b.tss             IN ($in_list)
            )
        ) t
        ORDER BY seqnames, start
    ";

    $result = $db_conn->query($sql);
    if (!$result) return [];

    $ranges = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure start <= end regardless of strand arithmetic
        $s = (int)$row['start'];
        $e = (int)$row['end'];
        if ($s > $e) [$s, $e] = [$e, $s];
        $ranges[] = ['chr' => $row['seqnames'], 'start' => $s, 'end' => $e];
    }
    return $ranges;
}

/**
 * Builds a UNION-all data query across multiple TSS ranges,
 * identical in structure to build_knockout_union_query().
 */
function build_crispri_union_query(
    string $table,
    string $label,
    array  $ranges,
    int    $rows_limit,
    int    $offset,
    string $searchTerm,
    string $sortCol,
    string $sortDir,
    int    $total_cap = 1000000
): string {
    $branches = array_map(
        static fn($r) => '(' . build_result_query($table, $label, $r['chr'], $r['start'], $r['end']) . ')',
        $ranges
    );
    $union = 'SELECT DISTINCT * FROM (' . implode(' UNION ALL ', $branches) . ') _union LIMIT ' . $total_cap;
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);
    $sql   = "SELECT base.* FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql  .= " ORDER BY $order";
    if ($rows_limit > 0) $sql .= " LIMIT $offset, $rows_limit";
    return $sql;
}

function build_crispri_count_query(
    string $table,
    string $label,
    array  $ranges,
    string $searchTerm,
    int    $total_cap = 1000000
): string {
    $branches = array_map(
        static fn($r) => '(' . build_result_query($table, $label, $r['chr'], $r['start'], $r['end']) . ')',
        $ranges
    );
    $union = 'SELECT DISTINCT * FROM (' . implode(' UNION ALL ', $branches) . ') _union LIMIT ' . $total_cap;
    $where = build_search_clause($label, $searchTerm);
    $sql   = "SELECT COUNT(*) AS cnt FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    return $sql;
}

// ── Run a query pair (count + data) and return result arrays ──────────────────

function run_query_pair(
    string $count_sql,
    string $data_sql,
    mysqli $db_conn,
    string $label
): array {
    $count_result = $db_conn->query($count_sql);
    if (!$count_result) {
        return ['error' => 'Count query failed: ' . $db_conn->error];
    }
    $total = (int)($count_result->fetch_assoc()['cnt'] ?? 0);
    $data_result = $db_conn->query($data_sql);
    if (!$data_result) {
        return ['error' => 'Data query failed: ' . $db_conn->error];
    }

    return [
        'total'  => $total,
        'fields' => $data_result->fetch_fields(),
        'items'  => $data_result->fetch_all(),
    ];
}

// ── Request validation ─────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'message' => 'POST only.'], 405);
}

$editingType     = post_string('editing_type');
$genome          = post_string('genome', 'hg38');
$searchLoad      = post_string('search_load', 'both');
$searchLoadLower = strtolower($searchLoad);
$searchTerm      = post_string('table_search', '');
$sortCol         = post_string('sort_col', 'Cut Site');
$sortDir         = post_string('sort_dir', 'asc');
$requestedGroup  = post_string('group');

if ($editingType === '') {
    respond(['ok' => false, 'message' => 'Missing editing_type.'], 422);
}

// ── Pagination ─────────────────────────────────────────────────────────────────
$allowed_rpp   = [25, 50, 100, 500, 0];
$rows_per_page = post_int('rows_per_page', 50);
if (!in_array($rows_per_page, $allowed_rpp, true)) $rows_per_page = 50;
$page   = max(1, (int)($_POST['page'] ?? 1));
$offset = $rows_per_page > 0 ? ($page - 1) * $rows_per_page : 0;

$include_cas9  = $searchLoadLower !== 'cas12a';
$include_cas12 = $searchLoadLower !== 'cas9';

// ── Payload skeleton ───────────────────────────────────────────────────────────
$payload = [
    'editing_type'   => $editingType,
    'genome'         => $genome,
    'search_load'    => $searchLoad,
    'mode'           => null,
    'criteria'       => [],
    'files'          => [],
    'enabled_groups' => [],
    'group'          => $requestedGroup,
    'total'          => 0,
    'page'           => $page,
    'rows_per_page'  => $rows_per_page,
    'table_search'   => $searchTerm,
    'sort_col'       => $sortCol,
    'sort_dir'       => strtolower($sortDir) === 'desc' ? 'desc' : 'asc',
    'fields'         => [],
    'items'          => [],
];

// ── File metadata ──────────────────────────────────────────────────────────────
foreach ($_FILES as $fieldName => $file) {
    if (is_array($file['name'])) {
        for ($i = 0; $i < count($file['name']); $i++) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $payload['files'][] = [
                'field' => $fieldName,
                'name'  => (string)$file['name'][$i],
                'type'  => (string)($file['type'][$i] ?? ''),
                'size'  => (int)($file['size'][$i] ?? 0),
                'error' => (int)($file['error'][$i] ?? 0),
            ];
        }
    } else {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $payload['files'][] = [
                'field' => $fieldName,
                'name'  => (string)$file['name'],
                'type'  => (string)($file['type'] ?? ''),
                'size'  => (int)($file['size'] ?? 0),
                'error' => (int)($file['error'] ?? 0),
            ];
        }
    }
}

// ── Editing-type branching ─────────────────────────────────────────────────────
switch (strtolower($editingType)) {

    // ── Cutting ───────────────────────────────────────────────────────────────
    case 'cutting':
        $application = post_string('application');
        $payload['mode'] = 'cutting';
        $payload['criteria']['application'] = $application !== '' ? $application : null;

        $all_groups = [];
        if ($include_cas9)  $all_groups[] = 'cas9';
        if ($include_cas12) $all_groups[] = 'cas12';
        $payload['enabled_groups'] = $all_groups;

        $validGroup = in_array($requestedGroup, ['cas9', 'cas12'], true)
            && (($requestedGroup === 'cas9' && $include_cas9) || ($requestedGroup === 'cas12' && $include_cas12));

        // ── Knockout ──────────────────────────────────────────────────────────
        if ($application === 'knockout') {
            $gene_symbols = split_multi(post_string('gene_symbol'));
            $ensembl_ids  = split_multi(post_string('ensembl_gene_id'));
            $payload['criteria']['gene_symbol']     = $gene_symbols;
            $payload['criteria']['ensembl_gene_id'] = $ensembl_ids;

            if ($validGroup) {
                $cds_rows = knockout_cds_rows($gene_symbols, $ensembl_ids);
                if (empty($cds_rows)) {
                    $payload['total'] = 0;
                } else {
                    $table     = $requestedGroup === 'cas9' ? 'human_cas9' : 'human_cas12';
                    $count_sql = build_knockout_count_query($table, $requestedGroup, $cds_rows, $searchTerm);
                    $data_sql  = build_knockout_union_query($table, $requestedGroup, $cds_rows, $rows_per_page, $offset, $searchTerm, $sortCol, $sortDir);
                    $qr        = run_query_pair($count_sql, $data_sql, $db_conn, $requestedGroup);
                    if (isset($qr['error'])) {
                        respond(['ok' => false, 'message' => $qr['error']], 500);
                    }
                    $payload['total']  = $qr['total'];
                    $payload['fields'] = $qr['fields'];
                    $payload['items']  = $qr['items'];
                }
            }

        // ── Deletion of exons ─────────────────────────────────────────────────
        } elseif ($application === 'deletion-exons') {
            $exon_choice  = post_string('exon-choice');
            $exon_inputs  = split_multi(post_string('exon_input'));
            $max_distance = post_int('max_distance', 1000) ?? 1000;

            $payload['criteria']['exon_choice']  = $exon_choice;
            $payload['criteria']['exon_input']   = $exon_inputs;
            $payload['criteria']['max_distance'] = $max_distance;

            if ($validGroup) {
                $ranges = deletion_exon_ranges($exon_choice, $exon_inputs);
                if (empty($ranges)) {
                    $payload['total'] = 0;
                } else {
                    $table   = $requestedGroup === 'cas9' ? 'human_cas9' : 'human_cas12';
                    $sql     = build_exon_union_query($table, $requestedGroup, $ranges, $max_distance);
                    $result  = $db_conn->query($sql);
                    if (!$result) {
                        respond(['ok' => false, 'message' => 'Query failed: ' . $db_conn->error], 500);
                    }

                    $raw_fields = $result->fetch_fields();
                    $raw_rows   = $result->fetch_all();

                    $col_map = [];
                    foreach ($raw_fields as $i => $f) {
                        $col_map[$f->name] = $i;
                    }

                    $filtered_rows = apply_exon_filter($raw_rows, $col_map);

                    $response_fields = array_values(array_filter(
                        $raw_fields,
                        static fn($f) => $f->name !== '_exon_range'
                    ));

                    $clean_col_map = [];
                    foreach ($response_fields as $i => $f) {
                        $clean_col_map[$f->name] = $i;
                    }

                    if (trim($searchTerm) !== '') {
                        $needle = mb_strtolower(trim($searchTerm));
                        $filtered_rows = array_values(array_filter(
                            $filtered_rows,
                            static function (array $row) use ($needle): bool {
                                foreach ($row as $cell) {
                                    if ($cell !== null && str_contains(mb_strtolower((string)$cell), $needle)) {
                                        return true;
                                    }
                                }
                                return false;
                            }
                        ));
                    }

                    $sort_idx = $clean_col_map[$sortCol] ?? ($clean_col_map['Distance'] ?? null);
                    if ($sort_idx !== null) {
                        $dir = strtolower($sortDir) === 'desc' ? -1 : 1;
                        usort($filtered_rows, static function (array $a, array $b) use ($sort_idx, $dir): int {
                            $av = $a[$sort_idx] ?? null;
                            $bv = $b[$sort_idx] ?? null;
                            if ($av === null) return $dir;
                            if ($bv === null) return -$dir;
                            $an = is_numeric($av) ? (float)$av : null;
                            $bn = is_numeric($bv) ? (float)$bv : null;
                            if ($an !== null && $bn !== null) return ($an <=> $bn) * $dir;
                            return strcmp((string)$av, (string)$bv) * $dir;
                        });
                    }

                    $total     = count($filtered_rows);
                    $page_rows = $rows_per_page > 0
                        ? array_slice($filtered_rows, $offset, $rows_per_page)
                        : $filtered_rows;

                    $payload['total']  = $total;
                    $payload['fields'] = $response_fields;
                    $payload['items']  = $page_rows;
                }
            }

        // ── Deletion of fragments ─────────────────────────────────────────────
        } elseif ($application === 'deletion-fragments') {
            $fragment_coords = post_string('fragment_coords');
            $fragment_search = post_string('fragment-search', 'within');
            $lower_upper     = post_int('lower_upper', 1000) ?? 1000;

            $payload['criteria']['fragment_coords'] = $fragment_coords;
            $payload['criteria']['fragment_search'] = $fragment_search;
            $payload['criteria']['lower_upper']     = $lower_upper;

            $colon_pos = stripos($fragment_coords, ':');
            $dash_pos  = stripos($fragment_coords, '-');

            if ($colon_pos === false || $dash_pos === false || $dash_pos <= $colon_pos) {
                $payload['error'] = 'invalid_coords';
            } elseif ($validGroup) {
                $var_chr   = preg_replace('/[^a-zA-Z0-9_]/', '', substr($fragment_coords, 0, $colon_pos));
                $var_start = (int)substr($fragment_coords, $colon_pos + 1, $dash_pos - $colon_pos - 1);
                $var_end   = (int)substr($fragment_coords, $dash_pos + 1);
                $table     = $requestedGroup === 'cas9' ? 'human_cas9' : 'human_cas12';

                if ($fragment_search === 'outside') {
                    $count_sql = build_outside_count_query($table, $requestedGroup, $var_chr, $var_start, $var_end, $lower_upper, $searchTerm);
                    $data_sql  = build_outside_query($table, $requestedGroup, $var_chr, $var_start, $var_end, $lower_upper, $rows_per_page, $offset, $searchTerm, $sortCol, $sortDir);
                } else {
                    $count_sql = build_count_query($table, $var_chr, $var_start, $var_end);
                    $data_sql  = build_filtered_query($table, $requestedGroup, $var_chr, $var_start, $var_end, $rows_per_page, $offset, $searchTerm, $sortCol, $sortDir);
                }

                $qr = run_query_pair($count_sql, $data_sql, $db_conn, $requestedGroup);
                if (isset($qr['error'])) {
                    respond(['ok' => false, 'message' => $qr['error']], 500);
                }
                $payload['total']  = $qr['total'];
                $payload['fields'] = $qr['fields'];
                $payload['items']  = $qr['items'];
            }
        }
        break;

    // ── CRISPRi ───────────────────────────────────────────────────────────────
    case 'crispri':
        $payload['mode'] = 'crispri';

        // Collect all inputs into a single list for the unified TSS query
        $all_inputs = array_values(array_unique(array_merge(
            split_multi(post_string('crispri_gene_symbol')),
            split_multi(post_string('crispri_ensembl_id')),
            split_multi(post_string('crispri_tss_coordinate'))
        )));
        $upstream   = post_int('crispri_upstream',   -100) ?? -100;
        $downstream = post_int('crispri_downstream',  500) ?? 500;

        $payload['criteria']['inputs']     = $all_inputs;
        $payload['criteria']['upstream']   = $upstream;
        $payload['criteria']['downstream'] = $downstream;

        $all_groups = [];
        if ($include_cas9)  $all_groups[] = 'cas9';
        if ($include_cas12) $all_groups[] = 'cas12';
        $payload['enabled_groups'] = $all_groups;

        $validGroup = in_array($requestedGroup, ['cas9', 'cas12'], true)
            && (($requestedGroup === 'cas9' && $include_cas9) || ($requestedGroup === 'cas12' && $include_cas12));

        if ($validGroup) {
            $ranges = crispri_tss_ranges($all_inputs, $upstream, $downstream);
            if (empty($ranges)) {
                $payload['total'] = 0;
            } else {
                $table     = $requestedGroup === 'cas9' ? 'human_cas9' : 'human_cas12';
                $count_sql = build_crispri_count_query($table, $requestedGroup, $ranges, $searchTerm);
                $data_sql  = build_crispri_union_query($table, $requestedGroup, $ranges, $rows_per_page, $offset, $searchTerm, $sortCol, $sortDir);
                $qr        = run_query_pair($count_sql, $data_sql, $db_conn, $requestedGroup);
                if (isset($qr['error'])) {
                    respond(['ok' => false, 'message' => $qr['error']], 500);
                }
                $payload['total']  = $qr['total'];
                $payload['fields'] = $qr['fields'];
                $payload['items']  = $qr['items'];
            }
        }
        break;

    // ── CRISPRa ───────────────────────────────────────────────────────────────
    case 'crispra':
    case 'crisprpa':
        $payload['mode'] = 'crispra';

        $all_inputs = array_values(array_unique(array_merge(
            split_multi(post_string('crisprpa_gene_symbol')),
            split_multi(post_string('crisprpa_ensembl_id')),
            split_multi(post_string('crisprpa_tss_coordinate'))
        )));
        $upstream   = post_int('crisprpa_upstream',   -300) ?? -300;
        $downstream = post_int('crisprpa_downstream',    0) ?? 0;

        $payload['criteria']['inputs']     = $all_inputs;
        $payload['criteria']['upstream']   = $upstream;
        $payload['criteria']['downstream'] = $downstream;

        $all_groups = [];
        if ($include_cas9)  $all_groups[] = 'cas9';
        if ($include_cas12) $all_groups[] = 'cas12';
        $payload['enabled_groups'] = $all_groups;

        $validGroup = in_array($requestedGroup, ['cas9', 'cas12'], true)
            && (($requestedGroup === 'cas9' && $include_cas9) || ($requestedGroup === 'cas12' && $include_cas12));

        if ($validGroup) {
            $ranges = crispri_tss_ranges($all_inputs, $upstream, $downstream);
            if (empty($ranges)) {
                $payload['total'] = 0;
            } else {
                $table     = $requestedGroup === 'cas9' ? 'human_cas9' : 'human_cas12';
                $count_sql = build_crispri_count_query($table, $requestedGroup, $ranges, $searchTerm);
                $data_sql  = build_crispri_union_query($table, $requestedGroup, $ranges, $rows_per_page, $offset, $searchTerm, $sortCol, $sortDir);
                $qr        = run_query_pair($count_sql, $data_sql, $db_conn, $requestedGroup);
                if (isset($qr['error'])) {
                    respond(['ok' => false, 'message' => $qr['error']], 500);
                }
                $payload['total']  = $qr['total'];
                $payload['fields'] = $qr['fields'];
                $payload['items']  = $qr['items'];
            }
        }
        break;

    default:
        $payload['mode'] = 'unknown';
        break;
}

respond(['ok' => true, 'data' => $payload]);
