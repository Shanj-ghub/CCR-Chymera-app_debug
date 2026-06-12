<?php include __DIR__ . '/include/header.php'; ?>

<section class="page-title">
  <h2>CHyMErA-Design, the CHyMErA Hybrid Guide RNA Design Tool</h2>
</section>

<section class="content-section">
  <p>
    CHyMErA-Design facilitates the selection of Cas9 and Cas12a gRNA pairs for combinatorial genetic perturbations. This tool allows the selection of scored Cas9 and Cas12a guides, generation of hybrid guide (hg)RNA constructs and visualization of target sites and fragment deletions.
  </p>

  <p>
    The online tool provides a simple interface that first requires the user to select between the human and mouse genomes (hg38 or mm10, respectively), followed by choosing one of the four below mentioned applications:
  </p>

  <ul class="plist">
    <li>Knockout of genes (Cutting)</li>
    <li>Deletion of exons (Cutting)</li>
    <li>Deletion of other genetic elements (Cutting)</li>
    <li>Transcriptional interference (CRISPRi)</li>
    <li>Transcriptional activation (CRISPRa)</li>
  </ul>

  <p>
    Next the user specifies the target gene(s) or fragment using either an official gene symbol, Ensembl ID or genome coordinates. In addition, for exon and gene segment deletions, the maximum distance from the splice site and or fragment coordinates can be selected. Similarly, for CRISPRi and CRISPRa up- and downstream search windows from the transcription start sites (TSS) can be defined.
  </p>

  <p>
    The tool searches an established database and output a list of guides targeting the selected gene(s) or genetic fragment. The output contains the guide sequence, genome coordinates, target strand, PAM sequence, Ensembl IDs of targeted genes, exons and transcripts, and GENCODE annotations overlapping the target (cut) site, as well as on- and off-target scores for the respective nuclease. The guide lists are ranked based on the activity scores for Cas9 and Cas12a guides.
  </p>

  <p>
    For Cas9 gRNAs, on-target activity was estimated using RuleSet3 sequence-based (rs3.seq) and target-based (rs3.target) models with the Chen2013 tracrRNA version for all and CDS-targeting gRNAs, respectively. Cas12a gRNAs were scored using HydraNet to provide both sequence-based (HydraNet_Seq) activity scores for all guides and target-context (HydraNet_CDS) activity predictions for CDS-targeting guides.
  </p>

  <p>
    While the sequence-based scores are displayed for all applications, target-specific scores are only provided for gene knockout applications. HydraNet activity scores are provided for opCas12a (AsCas12a) as well as wild-type (WT) and D156R LbCas12a nuclease variants. The user may select to see scores for all or only selected Cas12a variants.
  </p>

  <p>
    Off-target potentials were assessed using GuideScan and GuideScan 2 which enumerated potential off-target sites within Hamming distances of 0–3. Specificity scores were then calculated by aggregating Cutting Frequency Determination (CFD) values, reflecting the likelihood of off-target cleavage based on mismatch number, position, and nucleotide identity relative to the guide sequence.
  </p>

  <p>
    A filter was applied to remove Cas12a spacers with &gt;= 50 perfect matches (H0 &gt;= 50) or &gt; 2,000 total matches within three Hamming distances, as enumerating off-target sites for those guides exceed the available computing power. This resulted in removal of &lt; 15% of all potential spacer sequences in the genome.
  </p>

  <p>
    Following querying the database for Cas9 and Cas12a guides and associated scores, the guide information for either both or only one of the nucleases can be downloaded as a text file for further analysis and customized ranking.
  </p>

  <p>
    In addition, a detailed information page is provided for each guide sequence listing additional features including guide cut sites, transcript and exon IDs and numbers. Furthermore, the distance and orientation to the pseudoexon border is listed for exon deletions and TSS details are provided for CRISPRi/a applications. Finally, a genome browser link is listed allowing inspection of the gRNA within the genomic context.
  </p>

  <h3>References</h3>

  <ol class="plist references">
    <li>
      DeWeirdt et al., Accounting for small variations in the tracrRNA sequence improves sgRNA activity predictions for CRISPR screening. Nature Communications, 2022.
      <a href="https://doi.org/10.1038/s41467-022-33024-2" target="_blank" rel="noopener">https://doi.org/10.1038/s41467-022-33024-2</a>
    </li>
    <li>
      Jeon et al., An Optimized Cas12a Toolkit for Scalable Combinatorial Genetic Screening and Single-Cell Transcriptomics. Submitted.
    </li>
    <li>
      Perez et al., GuideScan software for improved single and paired CRISPR guide RNA design. Nature Biotechnology, 2017.
      <a href="https://doi.org/10.1038/nbt.3804" target="_blank" rel="noopener">https://doi.org/10.1038/nbt.3804</a>
    </li>
    <li>
      Schmidt et al., Genome-wide CRISPR guide RNA design and specificity analysis with GuideScan2. Genome Biology, 2025.
      <a href="https://doi.org/10.1186/s13059-025-03488-8" target="_blank" rel="noopener">https://doi.org/10.1186/s13059-025-03488-8</a>
    </li>
    <li>
      Doench et al., Optimized sgRNA design to maximize activity and minimize off-target effects of CRISPR-Cas9. Nature Biotechnology, 2016.
      <a href="https://www.nature.com/articles/nbt.3437" target="_blank" rel="noopener">https://www.nature.com/articles/nbt.3437</a>
    </li>
    <li>
      DeWeirdt et al., Optimization of AsCas12a for combinatorial genetic screens in human cells. Nature Biotechnology, 2021.
      <a href="https://doi.org/10.1038/s41587-020-0600-6" target="_blank" rel="noopener">https://doi.org/10.1038/s41587-020-0600-6</a>
    </li>
    <li>
      Aregger et al., Application of CHyMErA Cas9-Cas12a combinatorial genome-editing platform for genetic interaction mapping and gene fragment deletion screening. Nature Protocols, 2021.
      <a href="https://doi.org/10.1038/s41596-021-00595-1" target="_blank" rel="noopener">https://doi.org/10.1038/s41596-021-00595-1</a>
    </li>
  </ol>
</section>

<?php include __DIR__ . '/include/footer.php'; ?>
