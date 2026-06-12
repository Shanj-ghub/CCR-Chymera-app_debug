<?php
/* includes/crisprapane.php */
?>
<div id="crisprpa-pane" class="search-pane" role="region" aria-label="CRISPRa search options" style="display:none;">
  <div class="form-group">
    <label for="crisprpa-search-type">Search Type</label>
    <select id="crisprpa-search-type" class="form-control" name="crispra_search_type">
      <option value="">Select...</option>
      <option value="gene">Gene Symbol</option>
      <option value="ensembl">Ensembl Gene ID</option>
      <option value="tss">TSS Coordinate</option>
    </select>
  </div>

  <!-- Gene name state -->
  <div id="crisprpa-state-gene" style="display:none;">
    <div class="form-group">
      <label for="crisprpa-gene-symbol">Gene Symbol</label>
      <input id="crisprpa-gene-symbol" name="crisprpa_gene_symbol" class="form-control" placeholder="Delineate multiple genes by a comma or semicolon" />
    </div>

    <div class="form-group two-col">
      <div>
        <label for="crisprpa-upstream">Upstream</label>
        <input id="crisprpa-upstream" name="crisprpa_upstream" type="number" class="form-control" value="-300" />
      </div>
      <div>
        <label for="crisprpa-downstream">Downstream</label>
        <input id="crisprpa-downstream" name="crisprpa_downstream" type="number" class="form-control" value="0" />
      </div>
    </div>

    <div class="form-group">
      <label>Upload a file</label>
      <div class="file-drop">Drag and Drop files here, or <a href="#">browse</a>.</div>
    </div>
  </div>

  <!-- Ensembl state -->
  <div id="crisprpa-state-ensembl" style="display:none;">
    <div class="form-group">
      <label for="crisprpa-ensembl-id">Ensembl Gene ID</label>
      <input id="crisprpa-ensembl-id" name="crisprpa_ensembl_id" class="form-control" placeholder="Delineate multiple ensembls with a comma or semicolon" />
    </div>

    <div class="form-group two-col">
      <div>
        <label for="crisprpa-upstream-ens">Upstream</label>
        <input id="crisprpa-upstream-ens" name="crisprpa_upstream_ens" type="number" class="form-control" value="-300" />
      </div>
      <div>
        <label for="crisprpa-downstream-ens">Downstream</label>
        <input id="crisprpa-downstream-ens" name="crisprpa_downstream_ens" type="number" class="form-control" value="0" />
      </div>
    </div>

    <div class="form-group">
      <label>Upload a file</label>
      <div class="file-drop">Drag and Drop files here, or <a href="#">browse</a>.</div>
    </div>
  </div>

  <!-- TSS state -->
  <div id="crisprpa-state-tss" style="display:none;">
    <div class="form-group">
      <label for="crisprpa-tss-coordinate">TSS Coordinate</label>
      <input id="crisprpa-tss-coordinate" name="crisprpa_tss_coordinate" class="form-control" placeholder="chr1:1234567" />
    </div>

    <div class="form-group horizontal-radios">
      <label>Gene Strand (for TSS Coordinate)</label>
      <label><input type="radio" name="crisprpa-gene-strand" value="+" checked /> +</label>
      <label><input type="radio" name="crisprpa-gene-strand" value="-" /> -</label>
    </div>

    <div class="form-group two-col">
      <div>
        <label for="crisprpa-upstream-tss">Upstream</label>
        <input id="crisprpa-upstream-tss" name="crisprpa_upstream_tss" type="number" class="form-control" value="-300" />
      </div>
      <div>
        <label for="crisprpa-downstream-tss">Downstream</label>
        <input id="crisprpa-downstream-tss" name="crisprpa_downstream_tss" type="number" class="form-control" value="0" />
      </div>
    </div>

    <div class="form-group">
      <label>Upload a file</label>
      <div class="file-drop">Drag and Drop files here, or <a href="#">browse</a>.</div>
    </div>
  </div>
</div>
