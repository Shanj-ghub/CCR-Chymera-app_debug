<?php include __DIR__ . '/include/header.php'; ?>

<article class="home">
  <div class="hero">
    <img src="/chymera/images/ChymeraLogo.png" alt="hero" style="max-width:100%;border:1px solid #eee;padding:8px;background:#fff" />
    <h2>The CHyMErA Combinatorial Genome Editing System</h2>
    <br>
    <a class="btn-search" href="/chymera/search.php">Search for Guides</a> 
  </div>
  <br>

  <section class="intro">
    <h2>About CHyMErA</h2>
    <p>
      The CHyMErA system (Cas Hybrid for Multiplexed Editing and Screening Applications) is based on cell lines expressing nuclear <em>Streptococcus pyogenes</em> (Sp) Cas9 and <em>Lachnospiraceae bacterium</em> (Lb) or <em>Acidaminococcus</em> sp. (As) Cas12a nucleases along with a hybrid guide (hgRNA) expression cassette (Gonatopoulos-Pournatzis et al., Nature Biotech., 2020). Hybrid guide RNAs consists of a fusion of Cas9 and Cas12a gRNAs and are expressed under a single U6 promoter. The hgRNA transcript is subsequently cleaved by Cas12a using its intrinsic RNA-processing activity, which recognizes the direct repeat (DR) sequence and cleaves upstream of it, thereby liberating functional Cas9 and Cas12a gRNAs that form complexes with the respective nuclease for directed combinatorial genome editing. CHyMErA is suitable for a wide range of applications such as the high-throughput deletion of gene segments, including individual exons, as well as the concomitant targeting of two or more genes enabling the systematic mapping of genetic interactions.
    </p>
  </section>

  <section class="diagram">
    <img src="/chymera/images/ChymeraMappingOfGeneticInteractionsv2.png" alt="diagram" />
  </section>
</article>

<?php include __DIR__ . '/include/footer.php'; ?>
