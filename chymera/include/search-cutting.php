<?php
/* includes/cutting-pane.php */
?>
<div id="cutting-pane" class="search-pane" role="region" aria-label="Cutting search options" style="display:none;">
  <div class="form-group">
    <label for="application">Application</label>
    <select id="application" name="application" class="form-control">
      <option value="">Select...</option>
      <option value="knockout">Knockout of genes</option>
      <option value="deletion-exons">Deletion of exons</option>
      <option value="deletion-fragments">Deletion of other genetic fragments</option>
    </select>
  </div>

  <div id="target-definition" class="conditional-block">
    <h4 class="block-title">Target Definition</h4>

    <div class="form-group">
      <label for="gene-symbol">Gene Symbol</label>
      <input id="gene-symbol" name="gene_symbol" placeholder="Delineate multiple genes by a comma or semicolon" class="form-control" />
    </div>

    <div class="form-group">
      <label for="ensembl-gene-id">Ensembl Gene ID</label>
      <input id="ensembl-gene-id" name="ensembl_gene_id" placeholder="Delineate multiple ensembls with a comma or semicolon" class="form-control" />
    </div>

    <div class="form-group file-upload-block">
      <h4 class="block-title">Upload a file</h4>
      <input type="file" name="cutting_file" class="form-control" accept=".txt,.csv" />
    </div>
  </div>

  <div id="deletion-exons-block" class="conditional-block" style="display:none;">
    <h4 class="block-title">Deletion of exons</h4>
    <div class="form-group vertical-radios">
      <label><input type="radio" name="exon-choice" value="ensembl-exon" /> Ensembl Exon Id</label>
      <label><input type="radio" name="exon-choice" value="exon-coordinates" /> Exon Coordinates</label>
    </div>

    <div id="exon-inputs" style="margin-top:.5rem; display:none;">
      <div class="form-group">
        <label for="exon-input">Exon input</label>
        <input id="exon-input" name="exon_input" class="form-control" />
      </div>
      <div class="form-group">
        <label for="max-distance">Maximum distance from splice site</label>
        <input id="max-distance" name="max_distance" value="1000" class="form-control" />
      </div>
      <div class="form-group file-upload-block">
        <h4 class="block-title">Upload a file</h4>
        <input type="file" name="exon_file" class="form-control" accept=".txt,.csv" />
      </div>
    </div>
  </div>

  <div id="deletion-fragments-block" class="conditional-block" style="display:none;">
    <h4 class="block-title">Deletion of other genetic fragments</h4>
    <div class="form-group">
      <label for="fragment-coords">Fragment coordinates</label>
      <input id="fragment-coords" name="fragment_coords" class="form-control" />
    </div>

    <div class="form-group vertical-radios">
      <label><input type="radio" name="fragment-search" value="within" /> Search within coordinates</label>
      <label><input type="radio" name="fragment-search" value="outside" /> Search outside coordinates</label>
    </div>

    <div class="form-group" id="lower-upper-block" style="display:none; margin-top:.5rem;">
      <label for="lower-upper">Lower/Upper limit</label>
      <input id="lower-upper" name="lower_upper" value="1000" class="form-control" />
    </div>

    <!-- NOTE: No file upload shown for deletion-fragments per requirements -->
  </div>
</div>
