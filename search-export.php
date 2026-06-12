<?php
/**
 * search-export.php
 * Streams a .xlsx file with one sheet per cas type, full dataset (no pagination).
 */
declare(strict_types=1);

include_once($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');
include_once($_SERVER['DOCUMENT_ROOT'] . '/include/xlsxwriter.class.php');

$db_conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// ── Helpers ───────────────────────────────────────────────────────────────────

$APPLICATION_LABELS = [
    'knockout'           => 'Knockout of genes',
    'deletion-exons'     => 'Deletion of Exons',
    'deletion-fragments' => 'Fragment Deletion',
];

function post_string(string $key, string $default = ''): string
{
    if (!isset($_POST[$key]) || $_POST[$key] === null) return $default;
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
        'Cas Type', 'Guide Sequence', 'Guide Coordinates', 'Cut Site',
        'Targeted Strand', 'PAM Sequence', 'Gene Symbol', 'Ensembl Gene ID',
        'Gene Strand', 'Target Category', 'Exon ID',
    ];
    if ($outside) {
        $cols[] = 'Distance';
        $cols[] = 'Flanking Direction';
    }
    $cols = array_merge($cols, [
        'Overlap Exon', 'Overlap Intron', 'Overlap UTR', 'Overlap CDS',
        'Overlap 2nd Gene', 'Number Of Off-Targets',
        'Hamming_0', 'Hamming_1', 'Hamming_2', 'Hamming_3',
        'specificity', 'classification',
    ]);
    if ($label === 'cas12') {
        $cols = array_merge($cols, [
            'HydraNet CDS Lb.D156R', 'HydraNet CDS Lb.WT', 'HydraNet CDS As.op',
            'HydraNet Seq Lb.D156R', 'HydraNet Seq Lb.WT', 'HydraNet Seq As.op',
        ]);
    } else {
        $cols = array_merge($cols, ['RS3 Seq', 'RS3 Target', 'RS3 Seq Target']);
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
           cas.RS3_seq        AS `RS3 Seq`,
           cas.RS3_target     AS `RS3 Target`,
           cas.RS3_seq_target AS `RS3 Seq Target`";
}

function score_cols_inner(string $label): string
{
    return $label === 'cas12'
        ? ", HydraNet_CDS_Lb_D156R_Cas12a, HydraNet_CDS_Lb_WT_Cas12a, HydraNet_CDS_As_opCas12a,
               HydraNet_Seq_Lb_D156R_Cas12a, HydraNet_Seq_Lb_WT_Cas12a, HydraNet_Seq_As_opCas12a"
        : ", RS3_seq, RS3_target, RS3_seq_target";
}

// ── Search / order clause helpers ─────────────────────────────────────────────

function build_search_clause(string $label, string $searchTerm, bool $outside = false): string
{
    $searchTerm = trim($searchTerm);
    if ($searchTerm === '') return '';
    $parts  = array_map(static function (string $col): string {
        $safe = str_replace('`', '``', $col);
        return "COALESCE(CAST(base.`$safe` AS CHAR), '')";
    }, result_columns($label, $outside));
    $needle = escape_like(mb_strtolower($searchTerm));
    return "LOWER(CONCAT_WS(' ', " . implode(', ', $parts) . ")) LIKE '%$needle%' ESCAPE '\\\\'";
}

function build_order_clause(string $label, string $sortCol, string $sortDir, bool $outside = false): string
{
    $allowed = [];
    foreach (result_columns($label, $outside) as $col) {
        $safe          = str_replace('`', '``', $col);
        $allowed[$col] = "base.`$safe`";
    }
    $sortCol = isset($allowed[$sortCol]) ? $sortCol : 'Cut Site';
    $sortDir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
    return $allowed[$sortCol] . ' ' . $sortDir;
}

// ── Core SELECT for one coordinate range ──────────────────────────────────────
//
// FIX vs original: removed the full-table "GROUP BY gene_name" subquery that
// computed transcript_count by scanning all of human_annot. We now read
// transcript_count directly from the human_annot column (same as
// search-process.php), scoped to the same coordinate window.

function build_export_base_query(
    string $table,
    string $label,
    string $var_chr,
    int    $var_start,
    int    $var_end,
    string $extra_guide_where = ''   // optional additional filter on the guide subquery
): string {
    $guide_where = "seqnames = '$var_chr' AND start <= $var_end AND end >= $var_start";
    if ($extra_guide_where !== '') {
        $guide_where .= " AND $extra_guide_where";
    }
    $annot_where = "seqnames = '$var_chr' AND start <= $var_end AND end >= $var_start";

    $sc = score_cols_select($label);
    $si = score_cols_inner($label);

    return "
        SELECT DISTINCT
            '$label'           AS `Cas Type`,
            cas.guide_sequence AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand         AS `Targeted Strand`,
            pam_sequence       AS `PAM Sequence`,
            GROUP_CONCAT(DISTINCT gene_name          SEPARATOR ', ') AS `Gene Symbol`,
            GROUP_CONCAT(DISTINCT gene_id            SEPARATOR ', ') AS `Ensembl Gene ID`,
            GROUP_CONCAT(DISTINCT ens.strand         SEPARATOR ', ') AS `Gene Strand`,
            GROUP_CONCAT(DISTINCT ens.gene_type      SEPARATOR ', ') AS `Target Category`,
            GROUP_CONCAT(DISTINCT ens.exon_id        SEPARATOR ', ') AS `Exon ID`,
            GROUP_CONCAT(DISTINCT ens.transcript_id  SEPARATOR ', ') AS `Transcript ID`,
            GROUP_CONCAT(DISTINCT ens.exon_number    SEPARATOR ', ') AS `Exon Number`,
            CONCAT(count(distinct ens.transcript_id),'/',ens.transcript_count, ' (',
                   round(count(distinct ens.transcript_id) / ens.transcript_count * 100),
                   '%) ')                                            AS `Targeted Transcripts`,
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
            WHERE $annot_where
        ) ens ON cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY cas.seqnames, cas.start, cas.end, cas.strand
    ";
}

// ── Knockout export query ─────────────────────────────────────────────────────
//
// FIX vs original: group CDS rows by chromosome → one branch per chromosome
// instead of one branch per CDS row. For BRCA1 (~90 CDS rows on chr17) this
// reduces the UNION from ~90 independent full scans + joins to 1.

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

/**
 * Returns an OR-list WHERE fragment matching guides that overlap any of the
 * given CDS rows: (start <= X AND end >= Y) OR ...
 */
function cds_ranges_where(array $rows): string
{
    $parts = array_map(
        static fn($r) => "(start <= {$r['end']} AND end >= {$r['start']})",
        $rows
    );
    return '(' . implode(' OR ', $parts) . ')';
}

function build_knockout_export_query(
    string $table,
    string $label,
    array  $cds_rows,
    string $searchTerm,
    string $sortCol,
    string $sortDir
): string {
    // Group by chromosome → one branch per chr instead of one per CDS row
    $by_chr = [];
    foreach ($cds_rows as $r) {
        $by_chr[$r['chr']][] = $r;
    }

    $branches = [];
    foreach ($by_chr as $chr => $chr_rows) {
        $min_start = min(array_column($chr_rows, 'start'));
        $max_end   = max(array_column($chr_rows, 'end'));
        // Pass the OR-range list as the extra guide WHERE so only guides
        // overlapping an actual CDS segment are returned.
        $extra = cds_ranges_where($chr_rows);
        $branches[] = '(' . build_export_base_query($table, $label, $chr, $min_start, $max_end, $extra) . ')';
    }

    $union = 'SELECT DISTINCT * FROM (' . implode(' UNION ALL ', $branches) . ') _union';
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);
    $sql   = "SELECT base.* FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql  .= " ORDER BY $order";
    return $sql;
}

// ── CRISPRi/a: resolve TSS ranges ────────────────────────────────────────────

function crispri_tss_ranges(array $inputs, int $upstream, int $downstream): array
{
    global $db_conn;
    if (empty($inputs)) return [];

    $esc_vals = array_map(
        static fn($v) => "'" . mysqli_real_escape_string($db_conn, $v) . "'",
        $inputs
    );
    $in_list = implode(',', $esc_vals);

    $stripped_vals = array_map(
        static fn($v) => "'" . mysqli_real_escape_string($db_conn, preg_replace('/\.\d+$/', '', $v)) . "'",
        $inputs
    );
    $stripped_list = implode(',', $stripped_vals);

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
        $s = (int)$row['start'];
        $e = (int)$row['end'];
        if ($s > $e) [$s, $e] = [$e, $s];
        $ranges[] = ['chr' => $row['seqnames'], 'start' => $s, 'end' => $e];
    }
    return $ranges;
}

function build_crispri_export_query(
    string $table, string $label,
    array  $ranges,
    string $searchTerm, string $sortCol, string $sortDir
): string {
    $branches = array_map(
        static fn($r) => '(' . build_export_base_query($table, $label, $r['chr'], $r['start'], $r['end']) . ')',
        $ranges
    );
    $union = 'SELECT DISTINCT * FROM (' . implode(' UNION ALL ', $branches) . ') _union';
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);
    $sql   = "SELECT base.* FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql  .= " ORDER BY $order";
    return $sql;
}

// ── Deletion-of-fragments export queries ──────────────────────────────────────

function build_filtered_export_query(
    string $table, string $label,
    string $var_chr, int $var_start, int $var_end,
    string $searchTerm, string $sortCol, string $sortDir
): string {
    $base  = build_export_base_query($table, $label, $var_chr, $var_start, $var_end);
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);
    $sql   = "SELECT base.* FROM ($base) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql  .= " ORDER BY $order";
    return $sql;
}

function build_outside_export_query(
    string $table, string $label,
    string $var_chr, int $var_start, int $var_end, int $flank_limit,
    string $searchTerm, string $sortCol, string $sortDir
): string {
    $up_start = $var_start - $flank_limit;
    $down_end = $var_end   + $flank_limit;
    $sc = score_cols_select($label);
    $si = score_cols_inner($label);

    $shared_select = "
            '$label'           AS `Cas Type`,
            cas.guide_sequence AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand         AS `Targeted Strand`,
            pam_sequence       AS `PAM Sequence`,
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
            h0 + h1 + h2 + h3  AS `Number Of Off-Targets`,
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
              FROM $table WHERE $up_guide) cas,
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
              FROM $table WHERE $down_guide) cas,
             (SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
              FROM human_annot WHERE $down_annot) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end, '-', cas.strand)";

    $union = "($up_branch) UNION ALL ($down_branch)";
    $where = build_search_clause($label, $searchTerm, true);
    $order = build_order_clause($label, $sortCol, $sortDir, true);
    $sql   = "SELECT base.* FROM ($union) base";
    if ($where !== '') $sql .= " WHERE $where";
    $sql  .= " ORDER BY $order";
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
            $ranges[] = ['chr' => $chr, 'start' => $start - 50, 'end' => $end + 50];
        }
    }

    return $ranges;
}

// ── Deletion-of-exons: per-range exon filter ─────────────────────────────────

function apply_exon_filter(array $rows, array $col_map): array
{
    $idx_range     = $col_map['_exon_range']       ?? null;
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
        usort($rows_in_group, static fn($a, $b) =>
            (float)($a[$idx_distance] ?? 0) <=> (float)($b[$idx_distance] ?? 0)
        );

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

    foreach ($filtered as &$row) { array_splice($row, $idx_range, 1); }
    unset($row);

    return $filtered;
}

function fetch_exon_export_rows(
    string $table,
    string $label,
    array  $ranges,
    int    $max_distance,
    string $searchTerm,
    string $sortCol,
    string $sortDir
): array {
    global $db_conn;

    $all_branches = [];

    foreach ($ranges as $r) {
        $chr       = $r['chr'];
        $start     = $r['start'];
        $end       = $r['end'];
        $up_start  = $start - $max_distance;
        $down_end  = $end   + $max_distance;
        $range_tag = mysqli_real_escape_string($db_conn, "$chr:$start-$end");

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

    $union  = implode(' UNION ALL ', array_map(fn($b) => "($b)", $all_branches));
    $result = $db_conn->query("($union)");
    if (!$result) {
        http_response_code(500);
        exit("Query failed for $label: " . $db_conn->error);
    }

    $raw_fields = $result->fetch_fields();
    $raw_rows   = $result->fetch_all();

    $col_map = [];
    foreach ($raw_fields as $i => $f) {
        $col_map[$f->name] = $i;
    }

    $rows = apply_exon_filter($raw_rows, $col_map);

    if (trim($searchTerm) !== '') {
        $needle = mb_strtolower(trim($searchTerm));
        $rows   = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            foreach ($row as $cell) {
                if ($cell !== null && str_contains(mb_strtolower((string)$cell), $needle)) return true;
            }
            return false;
        }));
    }

    $clean_fields = array_values(array_filter($raw_fields, static fn($f) => $f->name !== '_exon_range'));
    $clean_map    = [];
    foreach ($clean_fields as $i => $f) { $clean_map[$f->name] = $i; }

    $sort_idx = $clean_map[$sortCol] ?? ($clean_map['Distance'] ?? null);
    if ($sort_idx !== null) {
        $dir = strtolower($sortDir) === 'desc' ? -1 : 1;
        usort($rows, static function (array $a, array $b) use ($sort_idx, $dir): int {
            $av = $a[$sort_idx] ?? null;
            $bv = $b[$sort_idx] ?? null;
            if ($av === null) return $dir;
            if ($bv === null) return -$dir;
            if (is_numeric($av) && is_numeric($bv)) return ((float)$av <=> (float)$bv) * $dir;
            return strcmp((string)$av, (string)$bv) * $dir;
        });
    }

    return ['fields' => $clean_fields, 'rows' => $rows];
}

// ── Request validation ────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only.');
}

$editingType      = post_string('editing_type');
$editingTypeLower = strtolower($editingType);
$application      = post_string('application');
$searchLoad       = post_string('search_load', 'both');
$searchLoadLower  = strtolower($searchLoad);
$searchTerm       = post_string('table_search', '');
$sortCol          = post_string('sort_col', 'Cut Site');
$sortDir          = post_string('sort_dir', 'asc');

$isCutting = $editingTypeLower === 'cutting';
$isCrispri = $editingTypeLower === 'crispri';
$isCrispra = in_array($editingTypeLower, ['crispra', 'crisprpa'], true);

if (!$isCutting && !$isCrispri && !$isCrispra) {
    http_response_code(422);
    exit('Export is only supported for Cutting, CRISPRi, and CRISPRa searches.');
}

if ($isCutting && !in_array($application, ['knockout', 'deletion-exons', 'deletion-fragments'], true)) {
    http_response_code(422);
    exit('Export is only supported for Knockout, Deletion of Exons, and Deletion of Fragments searches.');
}

$include_cas9  = $searchLoadLower !== 'cas12a';
$include_cas12 = $searchLoadLower !== 'cas9';

// ── Search parameters sheet ───────────────────────────────────────────────────

$writer       = new XLSXWriter();
$params_sheet = 'Search Parameters';
$writer->writeSheetHeader($params_sheet, ['Parameter' => 'string', 'Value' => 'string']);

$params = [
    'Editing Type' => $editingType,
    'Genome'       => post_string('genome', 'hg38'),
    'Search Load'  => $searchLoad,
];

if ($isCutting) {
    $params['Application'] = $APPLICATION_LABELS[$application] ?? $application;

    if ($application === 'knockout') {
        $tmp = post_string('gene_symbol');
        if ($tmp !== '') $params['Gene Symbol'] = $tmp;
        $tmp = post_string('ensembl_gene_id');
        if ($tmp !== '') $params['Ensembl Gene ID'] = $tmp;
    }
    if ($application === 'deletion-exons') {
        $tmp = post_string('exon-choice');
        if ($tmp !== '') $params['Exon Choice'] = $tmp;
        $tmp = post_string('exon_input');
        if ($tmp !== '') $params['Exon Input'] = $tmp;
        $params['Max Distance'] = (string)(post_int('max_distance', 1000) ?? 1000);
    }
    if ($application === 'deletion-fragments') {
        $fragment_coords = post_string('fragment_coords');
        $fragment_search = post_string('fragment-search', 'within');
        $lower_upper     = post_int('lower_upper', 1000) ?? 1000;
        if ($fragment_coords !== '') $params['Fragment Coordinates'] = $fragment_coords;
        $params['Fragment Search'] = $fragment_search;
        if ($fragment_search === 'outside') $params['Lower/Upper Limit'] = (string)$lower_upper;
    }
}

if ($isCrispri || $isCrispra) {
    $prefix = $isCrispri ? 'crispri' : 'crisprpa';
    $genes  = post_string($prefix . '_gene_symbol');
    $ens    = post_string($prefix . '_ensembl_id');
    $tss    = post_string($prefix . '_tss_coordinate');
    if ($genes !== '') $params['Gene Symbol']     = $genes;
    if ($ens   !== '') $params['Ensembl Gene ID'] = $ens;
    if ($tss   !== '') $params['TSS Coordinate']  = $tss;
    $params['Upstream']   = (string)(post_int($prefix . '_upstream',   $isCrispri ? -100 : -300) ?? ($isCrispri ? -100 : -300));
    $params['Downstream'] = (string)(post_int($prefix . '_downstream', $isCrispri ?  500 :    0) ?? ($isCrispri ?  500 :    0));
}

if ($searchTerm !== '') $params['Table Search'] = $searchTerm;
$params['Sort Column']      = $sortCol;
$params['Sort Direction']   = $sortDir;
$params['Export Generated'] = date('Y-m-d H:i:s');

foreach ($params as $k => $v) { $writer->writeSheetRow($params_sheet, [$k, $v]); }
unset($k, $v);

// ── One sheet per cas type ────────────────────────────────────────────────────

$groups = [];
if ($include_cas9)  $groups[] = 'cas9';
if ($include_cas12) $groups[] = 'cas12';

foreach ($groups as $label) {
    $table = $label === 'cas9' ? 'human_cas9' : 'human_cas12';

    // ── CRISPRi / CRISPRa ────────────────────────────────────────────────────
    if ($isCrispri || $isCrispra) {
        $prefix     = $isCrispri ? 'crispri' : 'crisprpa';
        $upstream   = post_int($prefix . '_upstream',   $isCrispri ? -100 : -300) ?? ($isCrispri ? -100 : -300);
        $downstream = post_int($prefix . '_downstream', $isCrispri ?  500 :    0) ?? ($isCrispri ?  500 :    0);

        $all_inputs = array_values(array_unique(array_merge(
            split_multi(post_string($prefix . '_gene_symbol')),
            split_multi(post_string($prefix . '_ensembl_id')),
            split_multi(post_string($prefix . '_tss_coordinate'))
        )));

        $ranges = crispri_tss_ranges($all_inputs, $upstream, $downstream);
        if (empty($ranges)) {
            http_response_code(422);
            exit('No TSS regions found for the given input.');
        }

        $sql    = build_crispri_export_query($table, $label, $ranges, $searchTerm, $sortCol, $sortDir);
        $result = $db_conn->query($sql);
        if (!$result) {
            http_response_code(500);
            exit("Query failed for $label: " . $db_conn->error);
        }

        $fields     = $result->fetch_fields();
        $header_fmt = [];
        foreach ($fields as $f) {
            if ($f->name === 'Cas Type') continue;
            $header_fmt[$f->name] = 'string';
        }
        $writer->writeSheetHeader($label, $header_fmt);
        while ($row = $result->fetch_row()) {
            $writer->writeSheetRow($label, array_slice($row, 1));
        }
        continue;
    }

    // ── Deletion of exons ─────────────────────────────────────────────────────
    if ($application === 'deletion-exons') {
        $exon_choice  = post_string('exon-choice');
        $exon_inputs  = split_multi(post_string('exon_input'));
        $max_distance = post_int('max_distance', 1000) ?? 1000;
        $ranges       = deletion_exon_ranges($exon_choice, $exon_inputs);

        if (empty($ranges)) {
            http_response_code(422);
            exit('No exon regions found for the given input.');
        }

        $export = fetch_exon_export_rows($table, $label, $ranges, $max_distance, $searchTerm, $sortCol, $sortDir);
        $fields = $export['fields'];
        $rows   = $export['rows'];

        $header_fmt = [];
        foreach ($fields as $f) {
            if ($f->name === 'Cas Type') continue;
            $header_fmt[$f->name] = 'string';
        }
        $writer->writeSheetHeader($label, $header_fmt);
        foreach ($rows as $row) {
            $writer->writeSheetRow($label, array_slice($row, 1));
        }
        continue;
    }

    // ── Knockout ──────────────────────────────────────────────────────────────
    if ($application === 'knockout') {
        $gene_symbols = split_multi(post_string('gene_symbol'));
        $ensembl_ids  = split_multi(post_string('ensembl_gene_id'));
        $cds_rows     = knockout_cds_rows($gene_symbols, $ensembl_ids);

        if (empty($cds_rows)) {
            http_response_code(422);
            exit('No CDS regions found for the given gene symbols / Ensembl IDs.');
        }

        $sql = build_knockout_export_query($table, $label, $cds_rows, $searchTerm, $sortCol, $sortDir);

    // ── Deletion of fragments ─────────────────────────────────────────────────
    } else {
        $fragment_coords = post_string('fragment_coords');
        $fragment_search = post_string('fragment-search', 'within');
        $lower_upper     = post_int('lower_upper', 1000) ?? 1000;

        $colon_pos = stripos($fragment_coords, ':');
        $dash_pos  = stripos($fragment_coords, '-');

        if ($colon_pos === false || $dash_pos === false || $dash_pos <= $colon_pos) {
            http_response_code(422);
            exit('Invalid fragment coordinates.');
        }

        $var_chr   = preg_replace('/[^a-zA-Z0-9_]/', '', substr($fragment_coords, 0, $colon_pos));
        $var_start = (int)substr($fragment_coords, $colon_pos + 1, $dash_pos - $colon_pos - 1);
        $var_end   = (int)substr($fragment_coords, $dash_pos + 1);

        $sql = $fragment_search === 'outside'
            ? build_outside_export_query($table, $label, $var_chr, $var_start, $var_end, $lower_upper, $searchTerm, $sortCol, $sortDir)
            : build_filtered_export_query($table, $label, $var_chr, $var_start, $var_end, $searchTerm, $sortCol, $sortDir);
    }

    // ── Stream SQL-driven result into sheet ───────────────────────────────────
    $result = $db_conn->query($sql);
    if (!$result) {
        http_response_code(500);
        exit("Query failed for $label: " . $db_conn->error);
    }

    $fields     = $result->fetch_fields();
    $header_fmt = [];
    foreach ($fields as $f) {
        if ($f->name === 'Cas Type') continue;
        $header_fmt[$f->name] = 'string';
    }
    $writer->writeSheetHeader($label, $header_fmt);
    while ($row = $result->fetch_row()) {
        $writer->writeSheetRow($label, array_slice($row, 1));
    }
}

// ── Stream to browser ─────────────────────────────────────────────────────────

$filename       = 'chymera-export-' . date('Ymd-His') . '.xlsx';
$download_token = post_string('download_token');
if ($download_token !== '') {
    setcookie('download_token', $download_token, [
        'expires'  => time() + 60,
        'path'     => '/',
        'samesite' => 'Strict',
    ]);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->writeToStdOut();
exit;
