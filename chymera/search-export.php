<?php
/**
 * search-export.php
 * Streams a .xlsx file with one sheet per cas type, full dataset (no pagination).
 */
declare(strict_types=1);

include_once($_SERVER['DOCUMENT_ROOT'] . '/chymera/config/db.php');
include_once($_SERVER['DOCUMENT_ROOT'] . '/chymera/include/xlsxwriter.class.php');

$db_conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

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

function build_export_base_query(string $table, string $label, string $var_chr, int $var_start, int $var_end): string
{
    $annot_where = "
        (seqnames = '$var_chr' AND start <= $var_start AND end >= $var_start)
        OR (seqnames = '$var_chr' AND start <= $var_end   AND end >= $var_end)
        OR (seqnames = '$var_chr' AND start >= $var_start AND end <= $var_end)
    ";

    $guide_where = "
        seqnames = '$var_chr'
        AND (end BETWEEN $var_start AND $var_end OR start BETWEEN $var_start AND $var_end)
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

    return "
        SELECT
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
            IF(INSTR(GROUP_CONCAT(DISTINCT gene_name, ', '),   ', ') > 0, 'TRUE', 'FALSE')                                                        AS `Overlap 2nd Gene`,
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
        ) cas,
        (
            SELECT seqnames, start, end, strand, type, gene_id, gene_type, gene_name, exon_id
            FROM human_annot
            WHERE $annot_where
        ) ens
        WHERE cas.start >= ens.start AND cas.end <= ens.end
        GROUP BY cas.guide_sequence, CONCAT(cas.seqnames, ':', cas.start, '-', cas.end)
    ";
}

function build_search_clause(string $label, string $searchTerm): string
{
    $searchTerm = trim($searchTerm);
    if ($searchTerm === '') {
        return '';
    }

    $parts = array_map(static function (string $col): string {
        $safe = str_replace('`', '``', $col);
        return "COALESCE(CAST(base.`$safe` AS CHAR), '')";
    }, result_columns($label));

    $needle = escape_like(mb_strtolower($searchTerm));
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

function build_filtered_export_query(
    string $table,
    string $label,
    string $var_chr,
    int $var_start,
    int $var_end,
    string $searchTerm,
    string $sortCol,
    string $sortDir
): string {
    $base  = build_export_base_query($table, $label, $var_chr, $var_start, $var_end);
    $where = build_search_clause($label, $searchTerm);
    $order = build_order_clause($label, $sortCol, $sortDir);

    $sql = "SELECT base.* FROM ($base) base";
    if ($where !== '') {
        $sql .= " WHERE $where";
    }
    $sql .= " ORDER BY $order";
    return $sql;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only.');
}

$editingType     = post_string('editing_type');
$searchLoad      = post_string('search_load', 'both');
$searchLoadLower = strtolower($searchLoad);
$searchTerm      = post_string('table_search', '');
$sortCol         = post_string('sort_col', 'Cut Site');
$sortDir         = post_string('sort_dir', 'asc');

if (
    strtolower($editingType) !== 'cutting'
    || post_string('application') !== 'deletion-fragments'
) {
    http_response_code(422);
    exit('Export is only supported for Cutting › Deletion of fragments searches.');
}

$fragment_coords = post_string('fragment_coords');
$colon_pos = stripos($fragment_coords, ':');
$dash_pos  = stripos($fragment_coords, '-');

if ($colon_pos === false || $dash_pos === false || $dash_pos <= $colon_pos) {
    http_response_code(422);
    exit('Invalid fragment coordinates.');
}

$var_chr   = preg_replace('/[^a-zA-Z0-9_]/', '', substr($fragment_coords, 0, $colon_pos));
$var_start = (int) substr($fragment_coords, $colon_pos + 1, $dash_pos - $colon_pos - 1);
$var_end   = (int) substr($fragment_coords, $dash_pos + 1);

$include_cas9  = $searchLoadLower !== 'cas12a';
$include_cas12 = $searchLoadLower !== 'cas9';

// ── Search parameters sheet ───────────────────────────────────────────────────
$writer = new XLSXWriter();
$params_sheet = 'Search Parameters';
$writer->writeSheetHeader($params_sheet, ['Parameter' => 'string', 'Value' => 'string']);

$editing_type     = post_string('editing_type');
$editing_type_lc  = strtolower($editing_type);
$application      = post_string('application');
$application_lc   = strtolower($application);
$search_load      = post_string('search_load', 'both');
$genome           = post_string('genome', 'hg38');

// Always-visible fields
$params = [
    'Editing Type' => $editing_type,
    'Genome'       => $genome,
    'Search Load'  => $search_load,
];

// Cutting-specific fields
if ($editing_type_lc === 'cutting') {
    $params['Application'] = $application;

    if ($application_lc === 'knockout') {
        $v = post_string('gene_symbol');
        if ($v !== '') $params['Gene Symbol'] = $v;
        $v = post_string('ensembl_gene_id');
        if ($v !== '') $params['Ensembl Gene ID'] = $v;
    }

    if ($application_lc === 'deletion-exons') {
        $v = post_string('exon_choice');
        if ($v !== '') $params['Exon Choice'] = $v;
        $v = post_string('exon_input');
        if ($v !== '') $params['Exon Input'] = $v;
        $v = post_int('max_distance', 1000);
        if ($v !== null) $params['Max Distance'] = (string) $v;
    }

    if ($application_lc === 'deletion-fragments') {
        $v = post_string('fragment_coords');
        if ($v !== '') $params['Fragment Coordinates'] = $v;
        $fragment_search = post_string('fragment_search', 'within');
        $params['Fragment Search'] = $fragment_search;
        if ($fragment_search === 'outside') {
            $v = post_int('lower_upper', 1000);
            if ($v !== null) $params['Lower/Upper Limit'] = (string) $v;
        }
    }
}

// CRISPRi-specific fields
if ($editing_type_lc === 'crispri') {
    $search_type = post_string('crispri_search_type');
    if ($search_type !== '') $params['Search Type'] = $search_type;

    if ($search_type === 'gene') {
        $v = post_string('crispri_gene_symbol');
        if ($v !== '') $params['Gene Symbol'] = $v;
        $params['Upstream']   = (string) post_int('crispri_upstream', -100);
        $params['Downstream'] = (string) post_int('crispri_downstream', 500);
    } elseif ($search_type === 'ensembl') {
        $v = post_string('crispri_ensembl_id');
        if ($v !== '') $params['Ensembl Gene ID'] = $v;
        $params['Upstream']   = (string) post_int('crispri_upstream_ens', -100);
        $params['Downstream'] = (string) post_int('crispri_downstream_ens', 500);
    } elseif ($search_type === 'tss') {
        $v = post_string('crispri_tss_coordinate');
        if ($v !== '') $params['TSS Coordinate'] = $v;
        $params['Gene Strand'] = post_string('crispri_gene_strand', '+');
        $params['Upstream']    = (string) post_int('crispri_upstream_tss', -100);
        $params['Downstream']  = (string) post_int('crispri_downstream_tss', 500);
    }
}

// CRISPRa-specific fields
if ($editing_type_lc === 'crispra' || $editing_type_lc === 'crisprpa') {
    $search_type = post_string('crisprpa_search_type');
    if ($search_type !== '') $params['Search Type'] = $search_type;

    if ($search_type === 'gene') {
        $v = post_string('crisprpa_gene_symbol');
        if ($v !== '') $params['Gene Symbol'] = $v;
        $params['Upstream']   = (string) post_int('crisprpa_upstream', -300);
        $params['Downstream'] = (string) post_int('crisprpa_downstream', 0);
    } elseif ($search_type === 'ensembl') {
        $v = post_string('crisprpa_ensembl_id');
        if ($v !== '') $params['Ensembl Gene ID'] = $v;
        $params['Upstream']   = (string) post_int('crisprpa_upstream_ens', -300);
        $params['Downstream'] = (string) post_int('crisprpa_downstream_ens', 0);
    } elseif ($search_type === 'tss') {
        $v = post_string('crisprpa_tss_coordinate');
        if ($v !== '') $params['TSS Coordinate'] = $v;
        $params['Gene Strand'] = post_string('crisprpa_gene_strand', '+');
        $params['Upstream']    = (string) post_int('crisprpa_upstream_tss', -300);
        $params['Downstream']  = (string) post_int('crisprpa_downstream_tss', 0);
    }
}

// Always append table search/sort and export timestamp
if ($searchTerm !== '') $params['Table Search'] = $searchTerm;
$params['Sort Column']    = $sortCol;
$params['Sort Direction'] = $sortDir;
$params['Export Generated'] = date('Y-m-d H:i:s');

foreach ($params as $key => $value) {
    $writer->writeSheetRow($params_sheet, [$key, $value]);
}

// ── One sheet per included table, streamed row by row ────────────────────────
$sheets = [];
if ($include_cas9)  $sheets['cas9']  = 'human_cas9';
if ($include_cas12) $sheets['cas12'] = 'human_cas12';

foreach ($sheets as $label => $table) {
    $sql    = build_filtered_export_query($table, $label, $var_chr, $var_start, $var_end, $searchTerm, $sortCol, $sortDir);
    $result = $db_conn->query($sql);

    if (!$result) {
        http_response_code(500);
        exit("Query failed for $label: " . $db_conn->error);
    }

    $fields     = $result->fetch_fields();
    $header_fmt = [];
    foreach ($fields as $f) {
        $header_fmt[$f->name] = 'string';
    }

    $writer->writeSheetHeader($label, $header_fmt);
    while ($row = $result->fetch_row()) {
        $writer->writeSheetRow($label, $row);
    }
}

// ── Stream to browser ─────────────────────────────────────────────────────────
$filename = 'chymera-export-' . date('Ymd-His') . '.xlsx';

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
