<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

include_once($_SERVER['DOCUMENT_ROOT'].'/chymera/config/db.php');

$db_conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// ── Debug logger ──────────────────────────────────────────────────────────────
$debug_log = '/var/www/html/chymera/process-debug.log';

function debug_log(string $message): void
{
    global $debug_log;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($debug_log, $line, FILE_APPEND | LOCK_EX);
}

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

function result_columns(string $label): array
{
    $cols = [
        'Cas Type',
        'Guide Sequence',
        'Guide Coordinates',
        'Cut Site',
        'Targeted Strand',
        'PAM Sequence',
        'Gene Name',
        'Gene ID',
        'Gene Strand',
        'Target Category',
        'Exon ID',
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
    ];

    if ($label === 'cas12') {
        $cols = array_merge($cols, [
            'On Target Score',
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

function build_result_query(
    string $table,
    string $label,
    string $var_chr,
    int $var_start,
    int $var_end,
    string $sortCol = 'Cut Site',
    string $sortDir = 'ASC',
    int $limit = 0,
    int $offset = 0
): string {
    $guide_where = "
        (seqnames = '$var_chr' AND end BETWEEN $var_start AND $var_end)
        OR (seqnames = '$var_chr' AND start BETWEEN $var_start AND $var_end)
    ";

    $annot_where = "
        (seqnames = '$var_chr' AND start <= $var_start AND end >= $var_start)
        OR (seqnames = '$var_chr' AND start <= $var_end   AND end >= $var_end)
        OR (seqnames = '$var_chr' AND start >= $var_start AND end <= $var_end)
    ";

    $score_cols = $label === 'cas12'
        ? ",
           cas.on_target_score                   AS `On Target Score`,
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

    $score_inner = $label === 'cas12'
        ? ", on_target_score,
               HydraNet_CDS_Lb_D156R_Cas12a, HydraNet_CDS_Lb_WT_Cas12a, HydraNet_CDS_As_opCas12a,
               HydraNet_Seq_Lb_D156R_Cas12a, HydraNet_Seq_Lb_WT_Cas12a, HydraNet_Seq_As_opCas12a"
        : ", RS3_seq, RS3_target, RS3_seq_target";

    // Map sort column name to actual SQL expression
    $sort_expr = match($sortCol) {
        'Guide Sequence'    => 'cas.guide_sequence',
        'Guide Coordinates' => 'CONCAT(cas.seqnames, \':\', guideseq_start, \'-\', guideseq_end)',
        'Targeted Strand'   => 'cas.strand',
        'PAM Sequence'      => 'pam_sequence',
        'Number Of Off-Targets' => 'h0 + h1 + h2 + h3',
        'Hamming_0'         => 'h0',
        'Hamming_1'         => 'h1',
        'Hamming_2'         => 'h2',
        'Hamming_3'         => 'h3',
        'specificity'       => 'specificity',
        'classification'    => 'classification',
        default             => 'CONCAT(cas.seqnames, \':\', cas.start, \'-\', cas.end)', // Cut Site
    };
    $sort_dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

    $limit_clause = '';
    if ($limit > 0) {
        $limit_clause = "LIMIT $offset, $limit";
    }

    return "
        SELECT DISTINCT
            '$label'              AS `Cas Type`,
            cas.guide_sequence    AS `Guide Sequence`,
            CONCAT(cas.seqnames, ':', guideseq_start, '-', guideseq_end) AS `Guide Coordinates`,
            CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)           AS `Cut Site`,
            cas.strand            AS `Targeted Strand`,
            pam_sequence          AS `PAM Sequence`,
            GROUP_CONCAT(DISTINCT gene_name     SEPARATOR ', ') AS `Gene Name`,
            GROUP_CONCAT(DISTINCT gene_id       SEPARATOR ', ') AS `Gene ID`,
            GROUP_CONCAT(DISTINCT ens.strand    SEPARATOR ', ') AS `Gene Strand`,
            GROUP_CONCAT(DISTINCT ens.gene_type SEPARATOR ', ') AS `Target Category`,
            GROUP_CONCAT(DISTINCT ens.exon_id   SEPARATOR ', ') AS `Exon ID`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') > 0, 'TRUE', 'FALSE')                                                               AS `Overlap Exon`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'exon') = 0 AND INSTR(GROUP_CONCAT(DISTINCT ens.type), 'gene') > 0, 'TRUE', 'FALSE')        AS `Overlap Intron`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'UTR')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap UTR`,
            IF(INSTR(GROUP_CONCAT(DISTINCT ens.type), 'CDS')  > 0, 'TRUE', 'FALSE')                                                               AS `Overlap CDS`,
            IF(INSTR(GROUP_CONCAT(DISTINCT gene_name SEPARATOR ', '), ', ') > 0, 'TRUE', 'FALSE')                                                  AS `Overlap 2nd Gene`,
            h0 + h1 + h2 + h3     AS `Number Of Off-Targets`,
            h0 AS Hamming_0, h1 AS Hamming_1, h2 AS Hamming_2, h3 AS Hamming_3,
            specificity, classification
            $score_cols
        FROM (
            SELECT guide_sequence, guideseq_start, guideseq_end,
                   seqnames, start, end, strand, pam_sequence,
                   h0, h1, h2, h3, specificity, classification
                   $score_inner
            FROM $table
            WHERE $guide_where
            LIMIT 1000000
        ) cas,
        (
            SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
            FROM human_annot
            WHERE $annot_where
        ) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)
        ORDER BY $sort_expr $sort_dir
        $limit_clause
    ";
}

function build_count_query(
    string $table,
    string $label,
    string $var_chr,
    int $var_start,
    int $var_end,
    string $searchTerm
): string {
    // Lightweight count — only 3 columns, no GROUP_CONCAT/score overhead.
    // searchTerm is intentionally ignored: applying it requires the full heavy
    // query and is the root cause of slowness. Counts shown in the UI will
    // reflect the unfiltered total; the data query still applies the filter.
    $guide_where = "
        seqnames = '$var_chr'
        AND (end BETWEEN $var_start AND $var_end OR start BETWEEN $var_start AND $var_end)
    ";

    $annot_where = "
        (ens.seqnames = '$var_chr' AND ens.start <= $var_start AND ens.end >= $var_start)
        OR (ens.seqnames = '$var_chr' AND ens.start <= $var_end   AND ens.end >= $var_end)
        OR (ens.seqnames = '$var_chr' AND ens.start >= $var_start AND ens.end <= $var_end)
    ";

    return "
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT DISTINCT cas.seqnames, cas.start, cas.end
            FROM (
                SELECT seqnames, start, end
                FROM $table
                WHERE $guide_where
                LIMIT 1000000
            ) cas
            JOIN human_annot ens
                ON cas.start >= ens.start
                AND cas.end  <= ens.end
            WHERE $annot_where
        ) t
    ";
}

function build_search_clause(string $label, string $searchTerm): string
{
    global $db_conn;

    $searchTerm = trim($searchTerm);
    if ($searchTerm === '') {
        return '';
    }

    $cols = result_columns($label);
    $parts = array_map(static function (string $col): string {
        $safe = str_replace('`', '``', $col);
        return "COALESCE(CAST(base.`$safe` AS CHAR), '')";
    }, $cols);

    $needle = escape_like(mb_strtolower($searchTerm));
    $needle = mysqli_real_escape_string($db_conn, $needle);
    return "LOWER(CONCAT_WS(' ', " . implode(', ', $parts) . ")) LIKE '%$needle%' ESCAPE '\\\\'";
}

function build_order_clause(string $label, string $sortCol, string $sortDir): string
{
    $allowed = [];
    foreach (result_columns($label) as $col) {
        $safe = str_replace('`', '``', $col);
        $allowed[$col] = "base.`$safe`";
    }

    $sortCol = isset($allowed[$sortCol]) ? $sortCol : 'Cut Site';
    $sortDir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

    return $allowed[$sortCol] . ' ' . $sortDir;
}

function build_filtered_query(
    string $table,
    string $label,
    string $var_chr,
    int $var_start,
    int $var_end,
    int $limit,
    int $offset,
    string $searchTerm,
    string $sortCol,
    string $sortDir
): string {
    // No search term: use fast direct query with ORDER BY + LIMIT inside,
    // avoiding the slow outer wrapper subquery
    if (trim($searchTerm) === '') {
        return build_result_query($table, $label, $var_chr, $var_start, $var_end, $sortCol, $sortDir, $limit, $offset);
    }

    // With search term: must wrap in outer subquery to apply LIKE filter.
    // This is inherently slower but only triggered when user types a search.
    $base  = build_result_query($table, $label, $var_chr, $var_start, $var_end);
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);

    $sql = "SELECT base.* FROM ($base) base WHERE $where ORDER BY $order";
    if ($limit > 0) {
        $sql .= " LIMIT $offset, $limit";
    }
    return $sql;
}

$editingType     = post_string('editing_type');
$genome          = post_string('genome', 'hg38');
$searchLoad      = post_string('search_load', 'both');
$searchLoadLower = strtolower($searchLoad);
$searchTerm      = post_string('table_search', '');
$sortCol         = post_string('sort_col', 'Cut Site');
$sortDir         = post_string('sort_dir', 'asc');

if ($editingType === '') {
    respond(['ok' => false, 'message' => 'Missing editing_type.'], 422);
}

// ── Pagination ────────────────────────────────────────────────────────────────
$allowed_rpp   = [25, 50, 100, 500, 0];
$rows_per_page = post_int('rows_per_page', 50);
if (!in_array($rows_per_page, $allowed_rpp, true)) {
    $rows_per_page = 50;
}
$page = max(1, (int)($_POST['page'] ?? 1));
$jump_page = post_string('jump_page');
if ($jump_page !== '' && ctype_digit($jump_page)) {
    $page = max(1, (int)$jump_page);
}
$offset = ($rows_per_page ?: PHP_INT_MAX) ? ($page - 1) * ($rows_per_page ?: PHP_INT_MAX) : 0;

// ── Payload skeleton ──────────────────────────────────────────────────────────
$payload = [
    'editing_type'   => $editingType,
    'genome'         => $genome,
    'search_load'    => $searchLoad,
    'mode'           => null,
    'criteria'       => [],
    'files'          => [],
    'page'           => $page,
    'rows_per_page'  => $rows_per_page,
    'table_search'   => $searchTerm,
    'sort_col'       => $sortCol,
    'sort_dir'       => strtolower($sortDir) === 'desc' ? 'desc' : 'asc',
];

// ── File metadata ─────────────────────────────────────────────────────────────
foreach ($_FILES as $fieldName => $file) {
    if (is_array($file['name'])) {
        for ($i = 0; $i < count($file['name']); $i++) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $payload['files'][] = [
                'field' => $fieldName,
                'name'  => (string) $file['name'][$i],
                'type'  => (string) ($file['type'][$i] ?? ''),
                'size'  => (int)   ($file['size'][$i] ?? 0),
                'error' => (int)   ($file['error'][$i] ?? 0),
            ];
        }
    } else {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $payload['files'][] = [
                'field' => $fieldName,
                'name'  => (string) $file['name'],
                'type'  => (string) ($file['type'] ?? ''),
                'size'  => (int)   ($file['size'] ?? 0),
                'error' => (int)   ($file['error'] ?? 0),
            ];
        }
    }
}

// ── Editing-type branching ────────────────────────────────────────────────────
switch (strtolower($editingType)) {
    case 'cutting':
        $application = post_string('application');
        $payload['mode'] = 'cutting';
        $payload['criteria']['application'] = $application !== '' ? $application : null;
        switch ($application) {
            case 'knockout':
                $payload['criteria']['gene_symbol']     = split_multi(post_string('gene_symbol'));
                $payload['criteria']['ensembl_gene_id'] = split_multi(post_string('ensembl_gene_id'));
                break;
            case 'deletion-exons':
                $payload['criteria']['exon_choice']  = post_string('exon_choice');
                $payload['criteria']['exon_input']   = split_multi(post_string('exon_input'));
                $payload['criteria']['max_distance'] = post_int('max_distance', 1000);
                break;
            case 'deletion-fragments':
                $payload['criteria']['fragment_coords'] = post_string('fragment_coords');
                $payload['criteria']['fragment_search'] = post_string('fragment_search', 'within');
                $payload['criteria']['lower_upper']     = post_int('lower_upper', 1000);
                break;
        }
        break;

    case 'crispri':
        $payload['mode'] = 'crispri';
        $searchType = post_string('crispri_search_type');
        $payload['criteria']['search_type'] = $searchType !== '' ? $searchType : null;
        if ($searchType === 'gene') {
            $payload['criteria']['gene_symbol'] = split_multi(post_string('crispri_gene_symbol'));
            $payload['criteria']['upstream']    = post_int('crispri_upstream', -100);
            $payload['criteria']['downstream']  = post_int('crispri_downstream', 500);
        } elseif ($searchType === 'ensembl') {
            $payload['criteria']['ensembl_gene_id'] = split_multi(post_string('crispri_ensembl_id'));
            $payload['criteria']['upstream']        = post_int('crispri_upstream_ens', -100);
            $payload['criteria']['downstream']      = post_int('crispri_downstream_ens', 500);
        } elseif ($searchType === 'tss') {
            $payload['criteria']['tss_coordinate'] = post_string('crispri_tss_coordinate');
            $payload['criteria']['gene_strand']    = post_string('crispri_gene_strand', '+');
            $payload['criteria']['upstream']       = post_int('crispri_upstream_tss', -100);
            $payload['criteria']['downstream']     = post_int('crispri_downstream_tss', 500);
        }
        break;

    case 'crispra':
    case 'crisprpa':
        $payload['mode'] = 'crispra';
        $searchType = post_string('crisprpa_search_type');
        $payload['criteria']['search_type'] = $searchType !== '' ? $searchType : null;
        if ($searchType === 'gene') {
            $payload['criteria']['gene_symbol'] = split_multi(post_string('crisprpa_gene_symbol'));
            $payload['criteria']['upstream']    = post_int('crisprpa_upstream', -300);
            $payload['criteria']['downstream']  = post_int('crisprpa_downstream', 0);
        } elseif ($searchType === 'ensembl') {
            $payload['criteria']['ensembl_gene_id'] = split_multi(post_string('crisprpa_ensembl_id'));
            $payload['criteria']['upstream']        = post_int('crisprpa_upstream_ens', -300);
            $payload['criteria']['downstream']      = post_int('crisprpa_downstream_ens', 0);
        } elseif ($searchType === 'tss') {
            $payload['criteria']['tss_coordinate'] = post_string('crisprpa_tss_coordinate');
            $payload['criteria']['gene_strand']    = post_string('crisprpa_gene_strand', '+');
            $payload['criteria']['upstream']       = post_int('crisprpa_upstream_tss', -300);
            $payload['criteria']['downstream']     = post_int('crisprpa_downstream_tss', 0);
        }
        break;

    default:
        $payload['mode'] = 'unknown';
        break;
}

// ── Run the deletion-fragments query (only when applicable) ───────────────────
$payload['result'] = ['status' => 'no_query', 'fields' => [], 'counts_by_group' => [], 'items' => []];

if (
    strtolower($editingType) === 'cutting'
    && ($payload['criteria']['application'] ?? '') === 'deletion-fragments'
    && !empty($payload['criteria']['fragment_coords'])
) {
    $fragment_coords = $payload['criteria']['fragment_coords'];
    $colon_pos = stripos($fragment_coords, ':');
    $dash_pos  = stripos($fragment_coords, '-');

    if ($colon_pos !== false && $dash_pos !== false && $dash_pos > $colon_pos) {
        $var_chr   = preg_replace('/[^a-zA-Z0-9_]/', '', substr($fragment_coords, 0, $colon_pos));
        $var_start = (int) substr($fragment_coords, $colon_pos + 1, $dash_pos - $colon_pos - 1);
        $var_end   = (int) substr($fragment_coords, $dash_pos + 1);

        $requested_group = post_string('group');
        $allowed_groups  = ['cas9', 'cas12'];
        $requested_group = in_array($requested_group, $allowed_groups, true) ? $requested_group : '';

        $include_cas9  = $searchLoadLower !== 'cas12a';
        $include_cas12 = $searchLoadLower !== 'cas9';

        $t_total_start = microtime(true);
        debug_log("Request started — region: $var_chr:$var_start-$var_end | group: '$requested_group' | search: '$searchTerm' | sort: $sortCol $sortDir | page: $page | rpp: $rows_per_page");

        // ── Count queries (lightweight — no GROUP_CONCAT or score columns) ────
        $counts_by_group = [];
        if ($include_cas9) {
            $count_sql = build_count_query('human_cas9', 'cas9', $var_chr, $var_start, $var_end, $searchTerm);
            debug_log("[cas9] COUNT SQL: $count_sql");
            $t1 = microtime(true);
            $r = $db_conn->query($count_sql);
            $counts_by_group['cas9'] = (int) ($r?->fetch_assoc()['cnt'] ?? 0);
            debug_log(sprintf("[cas9] COUNT time: %.3f s — result: %d rows", microtime(true) - $t1, $counts_by_group['cas9']));
        }
        if ($include_cas12) {
            $count_sql = build_count_query('human_cas12', 'cas12', $var_chr, $var_start, $var_end, $searchTerm);
            debug_log("[cas12] COUNT SQL: $count_sql");
            $t1 = microtime(true);
            $r = $db_conn->query($count_sql);
            $counts_by_group['cas12'] = (int) ($r?->fetch_assoc()['cnt'] ?? 0);
            debug_log(sprintf("[cas12] COUNT time: %.3f s — result: %d rows", microtime(true) - $t1, $counts_by_group['cas12']));
        }

        if ($requested_group === '') {
            $requested_group = $include_cas9 ? 'cas9' : 'cas12';
        }

        // ── Data query ────────────────────────────────────────────────────────
        $active_table = $requested_group === 'cas9' ? 'human_cas9' : 'human_cas12';
        $data_sql     = build_filtered_query($active_table, $requested_group, $var_chr, $var_start, $var_end, $rows_per_page, $offset, $searchTerm, $sortCol, $sortDir);

        debug_log("[$requested_group] DATA SQL: $data_sql");
        $t1 = microtime(true);
        $data_result = $db_conn->query($data_sql);
        debug_log(sprintf("[$requested_group] DATA query time: %.3f s", microtime(true) - $t1));

        if (!$data_result) {
            debug_log("[$requested_group] Query FAILED: " . $db_conn->error);
            respond(['ok' => false, 'message' => 'Query failed: ' . $db_conn->error], 500);
        }

        // ── Fetch rows ────────────────────────────────────────────────────────
        $t1 = microtime(true);
        $all_rows = $data_result->fetch_all();
        debug_log(sprintf("[$requested_group] fetch_all() time: %.3f s (%d rows fetched)", microtime(true) - $t1, count($all_rows)));

        $payload['result'] = [
            'status'          => 'success',
            'group'           => $requested_group,
            'fields'          => $data_result->fetch_fields(),
            'counts_by_group' => $counts_by_group,
            'items'           => $all_rows,
            'page'            => $page,
            'rows_per_page'   => $rows_per_page,
        ];

        // ── JSON encode + respond ─────────────────────────────────────────────
        $t1 = microtime(true);
        $json_payload = ['ok' => true, 'data' => $payload];
        $json_encoded = json_encode($json_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        debug_log(sprintf("JSON encode time: %.3f s (size: %s KB)", microtime(true) - $t1, number_format(strlen($json_encoded) / 1024, 1)));
        debug_log(sprintf("Total request time: %.3f s", microtime(true) - $t_total_start));
        debug_log(str_repeat('-', 60));

        http_response_code(200);
        echo $json_encoded;
        exit;

    } else {
        $payload['result']['status'] = 'invalid_coords';
    }
}

respond(['ok' => true, 'data' => $payload]);
