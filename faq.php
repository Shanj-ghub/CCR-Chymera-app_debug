<?php include __DIR__ . '/include/header.php'; ?>

<section class="page-title">
  <h2>Frequently Asked Questions</h2>
  <p>
    Below we provide answers to some frequently asked questions. For further information please refer to our protocol paper (Aregger et al., Nature Protocols, 2021) or contact Dr. Thomas Gonatopoulos-Pournatzis (thomas.gonatopoulos@nih.gov) or Dr. Michael Aregger (michael.aregger@nih.gov).
  </p>
</section>

<section class="faq">
  <div class="accordion">

    <!-- 1 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How does the CHyMErA system work and which CRISPR-Cas nucleases are used?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          CHyMErA is a CRISPR-based combinatorial genome editing platform for mammalian cells that is based on the expression of Streptococcus pyogenes (Sp)Cas9 and Lachnospiraceae bacterium (Lb) or Acidaminococcus sp. (As) Cas12a nucleases along with a hybrid guide (hg)RNA expression cassette.
        </p>
      </div></div>
    </div>

    <!-- 2 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>Where can I get the lentiviral Cas9, Cas12a and hgRNA plasmids from?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          Plasmids for the expression of Cas9 and Cas12a nucleases as well as for the cloning and expression of hgRNAs are available on Addgene:
        </p>

        <ul class="plist">
          <li>Lenti-Cas9-2A-Blast: lentiviral expression construct for human codon-optimized N-terminal FLAG-tagged Streptococcus pyogenes (Sp)Cas9 nuclease with C-terminal NLS and Blasticidin S resistance</li>
          <li>pLenti-D156R LbCas12a-8xNLS: Lentiviral vector expressing human codon-optimized D156R LbCas12a with 8xNLS and Neomycin/G418/Geneticin resistance.</li>
          <li>pLenti-opCas12a-6xNLS: Lentiviral vector expressing human codon-optimized (As) opCas12a with 6xNLS and Neomycin/G418/Geneticin resistance.</li>
          <li>pLCHKOv6: Lentiviral backbone for expressing U6 driven hybrid guide (hg)RNAs with BveI cloning sites and puromycin selection marker.</li>
          <li>Topo_SpCas9.tracr_LbCas12a.DR: TOPO vector for the cloning of the SpCas9 tracrRNA - LbCas12a Direct Repeat (DR) fragment into the pLCHKO hgRNA vector</li>
          <li>Topo_SpCas9.tracr_AsCas12a.DR: TOPO vector for the cloning of the SpCas9 tracrRNA - AsCas12a Direct Repeat (DR) fragment into the pLCHKO hgRNA vector.</li>
        </ul>

      </div></div>
    </div>

    <!-- 3 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>What hgRNA libraries are available to be used with CHyMErA?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <ul class="plist">
          <li>CHyMErA Cas12a Nuclease Optimization, Large-Scale Exon Deletion, and scCHyMErA-Seq Exon Deletion hgRNA Libraries</li>
          <li>LbCas12a Guide Optimization, AsCas12a Guide Optimization, CHyMErA LbCas12a Direct Repeat (DR) Variant and CHyMErA CRISPRi Optimization Libraries</li>
          <li>CRASP-Seq Pooled Libraries</li>
          <li>CHyMErA Paralog & Dual-targeting hgRNA pooled library</li>
          <li>CHyMErA Exon-deletion hgRNA pooled library</li>
        </ul>
      </div></div>
    </div>

    <!-- 4 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>What are the main applications of CHyMErA?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          CHyMErA can be used for the dual-targeting of single genes, targeting of gene pairs and the deletion of exons or other genetic fragments.
        </p>
      </div></div>
    </div>

    <!-- 5 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How are guides sorted in the search output?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          Guides are sorted based on their activity score (target score for gene knockout; sequence score for all other applications - see scoring information below). Users can select other columns or apply filters to customize sorting within the webtool. The search output can also be exported as an excel table to facilitate the process and save search results.
        </p>
      </div></div>
    </div>

    <!-- 6 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>Which algorithms have been used to score Cas9 and Cas12a guides?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          For Cas9 gRNAs, on-target activity was estimated using RuleSet3 sequence-based (rs3.seq) and target-based (rs3.target) models with the Chen2013 tracrRNA version (DeWeirdt et al., Nat. Comm., 2022) for all and CDS-targeting gRNAs, respectively. The combined RS3_Seq+Target score is recommended when available. Cas12a gRNAs were scored using HydraNet (Jeon et al., submitted), as described in this manuscript, to provide both sequence-based (HydraNet_Seq) activity scores for all guides and target-context (HydraNet_CDS) activity predictions for CDS-targeting guides.
        </p>

        <p>
          Off-target potentials were assessed using GuideScan and GuideScan 2 (Perez et al., Nat. Biotech., 2017; Perez et al., Nat. Comm., 2021), which enumerated potential off-target sites within Hamming distances of 0-3. Specificity scores were then calculated by aggregating Cutting Frequency Determination (CFD) (Doench et al., Nat. Biotech., 2016; DeWeirdt et al., Nat. Biotech., 2021) values, reflecting the likelihood of off-target cleavage based on mismatch number, position, and nucleotide identity relative to the guide sequence.
        </p>
      </div></div>
    </div>

    <!-- 7 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>Why do some guides lack target-specific scoring information?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          Some guides targeting coding sequences (CDS) do not have target-specific activity scores because their predicted cut sites overlap exon–intron boundaries. In addition, scoring is currently limited to guides that overlap exons present in primary (canonical) transcripts. Guides outside these regions are therefore not scored.
        </p>
      </div></div>
    </div>

    <!-- 8 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>Why do certain guides lack specificity information?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          A filter was applied to remove Cas12a spacers with >= 50 perfect matches (H0 >= 50) or > 2,000 total matches within three Hamming distances, as enumerating off-target sites for those guides exceed the available computing power. This resulted in removal of &lt; 15% of all potential spacer sequences in the genome. These highly promiscuous spacers should generally be avoided during guide selection.
        </p>
      </div></div>
    </div>

    <!-- 9 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>Which genome annotations have been used for guide scoring?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          The following annotation releases were used: human GRCh38 (hg38), GENCODE 38, Ensembl 104; mouse GRCm38 (mm10), Ensembl 102.
        </p>
      </div></div>
    </div>

    <!-- 10 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How should scores be used for guide selection?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          We recommend first filtering out guides with low specificity scores (e.g., &le; 0–0.3; ideally &le; 0–0.5). After this filtering step, rank the remaining guides by their activity score. While activity and specificity are key selection criteria, they should be considered alongside positional information, which can vary depending on the editing application (see also "How are hgRNAs designed for...?"). For example, when designing guides for gene knockout, it is generally best to target the first half of the coding sequence and to select guides that overlap all major transcript isoforms.
        </p>
      </div></div>
    </div>

    <!-- 11 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>What are the recommended guide lengths and PAM-sites for Cas9 and Cas12a?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          For Cas9, 20 nt long guides that target a spacer region with a 3'-end NGG PAM site (N being any nucleotide) should be used. For Cas12a, we recommend selecting 23 nt long guides that target a spacer region with a TTTV PAM site at the 5' end (V being any nucleotide other than T).
        </p>
      </div></div>
    </div>

    <!-- 12 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How are hgRNAs designed for gene inactivation/knockout?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          For gene inactivation, Cas9 and Cas12a sgRNAs are designed to target exonic regions. Cleavage of those exonic sites by either of the two nucleases results in DSB that are repaired by the endogenous DNA repair pathways. This repair process frequently introduces insertions or deletions that shift the reading frame, ultimately leading to gene inactivation. In general, we recommend targeting sequences within the first 15–45% of the coding region to maximize the likelihood of achieving a strong loss-of-function effect. When transcript usage is uncertain, guides that target all major transcript isoforms may be prioritized.
        </p>
      </div></div>
    </div>

    <!-- 13 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How are hgRNAs designed for exon deletion and excision of other genomic segments?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          For excision of individual exons Cas9 and Cas12a sgRNAs are designed to target intronic sequences flanking an exon of interest. Similarly, other genomic segments such as enhancers, promotors, protein domains or polyA signals can be targeted through combinatorial genome editing up- and downstream of those elements. We recommend positioning gRNAs at least 50 bp away from splice sites to minimize the risk that DSB-induced repair mutations disrupt splice-site recognition. Additionally, keeping the intended deletion fragment as small as feasible is advised, as smaller deletions generally exhibit higher excision efficiency.
        </p>

        <p>
          To facilitate exon deletion experiments, Cas9 and Cas12a gRNAs were curated to flank each protein-coding exon while avoiding cleavage within overlapping exons. This has been accomplished by collapsing overlapping exons (regardless of strand) into pseudoexons and then identifying corresponding flanking intronic sequences. CHyMErA-Design subsequently outputs gRNAs within a user-defined window with an upper limit of 1,000 base pairs from the boundaries of each collapsed pseudoexon. The tool also computes the gRNA cut site’s distance to the annotated pseudoexon boundary.
        </p>
      </div></div>
    </div>

    <!-- 14 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How are hgRNA designed for transcriptional repression (CRISPRi) or activation (CRISPRa)?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          For transcriptional modulation, catalytically inactivated nucleases coupled to transcriptional effector domains are recruited to the vicinity of transcription start sites (TSS). By default, the search window is set to -100 to +500 base pairs relative to the TSS for CRISPRi, and -300 to 0 base pairs for CRISPRa. When dual-targeting the same TSS with both nucleases, spacing the gRNAs sufficiently apart can help minimize competition for binding sites and reduce steric hindrance.
        </p>

        <p>
          For CRISPRi specifically, Cas9 and Cas12a guides tend to exhibit peak activity at or just downstream of the TSS, with activity dropping substantially for guides positioned +100 to +150 bp downstream.
        </p>
      </div></div>
    </div>

    <!-- 15 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>What controls are required for CHyMErA experiments?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          When designing CHyMErA hybrid constructs it is critical to control for dual DNA-targeting and potential off-target effects of either guide. Towards this we recommend that for each hgRNA guide pair (i.e. Cas9 guide and Cas12a guide), single-targeting constructs are designed whereby one of the two guides is replaced by a control guide targeting the intergenic space (i.e. guide that cuts DNA but is not expected to cause any specific fitness effect).
        </p>

        <p>
          Similarly, to differentiate between phenotypic effects stemming from fragment deletions vs. single cutting at either of the flaking sites, two 'single-targeting' control hgRNAs should be designed for each fragment deletion guide pair. To compare gene vs. segment-specific phenotypic effects, for genic fragment deletions we recommend to also include hgRNAs disrupting the gene harboring the fragment of interest.
        </p>

        <p>
          For further details and additional recommendations please refer to 'Box 3: Design features for CHyMErA hybrid guide (hg)RNAs' in our protocol paper (Aregger et al., Nature Protocols, 2021).
        </p>
      </div></div>
    </div>

    <!-- 16 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>How are hgRNAs cloned?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          Hybrid guide RNAs can be cloned using an optimized two-step cloning process involving two rounds of Golden Gate assembly into the pLCHKO lentiviral vector. In the first step, (pooled) oligos harboring two guide spacer sequences are ligated into the pLCHKO backbone. In the second step, the SpCas9 tracrRNA followed by a Lb/AsCas12a direct repeat is cloned into the pLCHKO vector thereby generating the functional hgRNA expression cassette.
        </p>

        <p>
          For focused experiments (not suitable for large hgRNA libraries), instead of cloning individual hgRNAs using restriction cloning, we recommend synthesizing the final pLCHKO hgRNA construct using DNA synthesis services provided by TWIST Bioscience or other synthetic DNA manufacturers.
        </p>

        <p>
          Our protocol paper provides a detailed description for both of these approaches (Aregger et al., Nature Protocols, 2021).
        </p>
      </div></div>
    </div>

    <!-- 17 -->
    <div class="acc-item">
      <div class="acc-head">
        <span>Where can I find further information about the CHyMErA tool and how to use it for genetic screens?</span>
        <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
      </div>
      <div class="acc-body"><div class="acc-body-inner">
        <p>
          For further information please refer to the manuscripts listed in the resource section, including our original CHyMErA manuscript (Gonatopoulos-Pournatzis et al., Nature Biotechnology, 2020) and our protocol paper (Aregger et al., Nature Protocols, 2021). You may also contact Dr. Thomas Gonatopoulos-Pournatzis (thomas.gonatopoulos@nih.gov) or Dr. Michael Aregger (michael.aregger@nih.gov) for additional details.
        </p>

        <div class="highlight-block" style="margin-top:14px;">
          Where can I find further information about the CHyMErA tool and how to use it for genetic screens?
        </div>

      </div></div>
    </div>

  </div>
</section>

<?php include __DIR__ . '/include/footer.php'; ?>