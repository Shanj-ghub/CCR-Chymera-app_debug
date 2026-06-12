<?php
/* includes/crispri-pane.php */
?>
<div id="crispri-pane" class="search-pane" role="region" aria-label="CRISPRi search options" style="display:none;">
  <div class="form-group">
    <label for="crispri-search-type">Search Type</label>
    <select id="crispri-search-type" class="form-control" name="crispri_search_type">
      <option value="">Select...</option>
      <option value="gene">Gene Symbol</option>
      <option value="ensembl">Ensembl Gene ID</option>
      <option value="tss">TSS Coordinate</option>
    </select>
  </div>

  <!-- Gene name state -->
  <div id="crispri-state-gene" style="display:none;">
    <div class="form-group">
      <label for="crispri-gene-symbol">Gene Symbol</label>
      <input id="crispri-gene-symbol" name="crispri_gene_symbol" class="form-control" placeholder="Delineate multiple genes by a comma or semicolon" />
    </div>

    <div class="form-group two-col">
      <div>
        <label for="crispri-upstream">Upstream</label>
        <input id="crispri-upstream" name="crispri_upstream" type="number" class="form-control" value="-100" />
      </div>
      <div>
        <label for="crispri-downstream">Downstream</label>
        <input id="crispri-downstream" name="crispri_downstream" type="number" class="form-control" value="500" />
      </div>
    </div>

    <div class="form-group">
      <label>Upload a file</label>
      <div class="file-drop">Drag and Drop files here, or <a href="#">browse</a>.</div>
    </div>
  </div>

  <!-- Ensembl state -->
  <div id="crispri-state-ensembl" style="display:none;">
    <div class="form-group">
      <label for="crispri-ensembl-id">Ensembl Gene ID</label>
      <input id="crispri-ensembl-id" name="crispri_ensembl_id" class="form-control" placeholder="Delineate multiple ensembls with a comma or semicolon" />
    </div>

    <div class="form-group two-col">
      <div>
        <label for="crispri-upstream-ens">Upstream</label>
        <input id="crispri-upstream-ens" name="crispri_upstream_ens" type="number" class="form-control" value="-100" />
      </div>
      <div>
        <label for="crispri-downstream-ens">Downstream</label>
        <input id="crispri-downstream-ens" name="crispri_downstream_ens" type="number" class="form-control" value="500" />
      </div>
    </div>

    <div class="form-group">
      <label>Upload a file</label>
      <div class="file-drop">Drag and Drop files here, or <a href="#">browse</a>.</div>
    </div>
  </div>

  <!-- TSS state -->
  <div id="crispri-state-tss" style="display:none;">
    <div class="form-group">
      <label for="crispri-tss-coordinate">TSS Coordinate</label>
      <input id="crispri-tss-coordinate" name="crispri_tss_coordinate" class="form-control" placeholder="chr1:1234567" />
    </div>

    <div class="form-group horizontal-radios">
      <label>Gene Strand (for TSS Coordinate)</label>
      <label><input type="radio" name="crispri-gene-strand" value="+" checked /> +</label>
      <label><input type="radio" name="crispri-gene-strand" value="-" /> -</label>
    </div>

    <div class="form-group two-col">
      <div>
        <label for="crispri-upstream-tss">Upstream</label>
        <input id="crispri-upstream-tss" name="crispri_upstream_tss" type="number" class="form-control" value="-100" />
      </div>
      <div>
        <label for="crispri-downstream-tss">Downstream</label>
        <input id="crispri-downstream-tss" name="crispri_downstream_tss" type="number" class="form-control" value="500" />
      </div>
    </div>

    <div class="form-group">
      <label>Upload a file</label>
      <div class="file-drop">Drag and Drop files here, or <a href="#">browse</a>.</div>
    </div>
  </div>
</div>
