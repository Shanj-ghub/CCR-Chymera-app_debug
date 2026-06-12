<?php include __DIR__ . '/include/header.php'; ?>

<section class="page-title">
  <h2>Applications of CHyMErA</h2>
</section>

<section class="content-section applications-page">
  <p class="intro">
    CHyMErA represents an efficient and versatile system for the combinatorial perturbation of genetic sites in mammalian cells. The Cas9 and Cas12a guides encoded within a hgRNA allow the programmed targeting of the two Cas nucleases to independent genomic sites.
  </p>

  <div class="diagram-placeholder large" aria-label="CHyMErA applications diagram placeholder">
    <img src="/images/40cbf35063664283216e.png" alt="Illustration showing where the chymera process begins." style="width:450px;"/>
  </div>

  <h2>Application Type:</h2>
  <div class="application-type-shell">
    <div class="application-type-nav" aria-label="Application Type navigation">
      <ul id="applicationTypeList">
        <li class="active" data-key="dual-targeting" tabindex="0">Dual-Targeting of Single Genes</li>
        <li data-key="interaction-mapping" tabindex="0">Genetic Interaction Mapping</li>
        <li data-key="higher-order" tabindex="0">Higher-Order Multiplexing</li>
        <li data-key="segment-deletion" tabindex="0">Genetic Segment Deletion</li>
        <li data-key="element-deletion" tabindex="0">Genetic Element Deletion</li>
        <li data-key="ncrna-deletion" tabindex="0">Non-Coding RNA Deletion</li>
      </ul>
    </div>

    <div class="application-type-content">
      <p class="lead" id="applicationLead">
        Increased editing efficiency through dual-targeting of single genes.
      </p>

      <h3 id="applicationTitle">Dual-Targeting of Single Gene</h3>

      <div class="diagram-placeholder diagram-card" id="applicationDiagram" aria-label="Application diagram placeholder" style="width:400px">
        Dual-targeting diagram placeholder
      </div>
    </div>
  </div>
</section>

<style>
.applications-page .intro{
  max-width: 920px;
  margin-bottom: 22px;
}

.diagram-placeholder{
  /* background: #f3f3f3; */
  /* border: 1px solid #e2e2e2; */
  color: #666;
  text-align: center;
  padding: 28px 18px;
  border-radius: 4px;
  margin: 18px 0 26px;
  font-size: 14px;
}

.diagram-placeholder.large{
  min-height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Connected tabs + content area */
.application-type-shell{
  display: grid;
  grid-template-columns: 310px 1fr;
  gap: 0;
  align-items: stretch;
  margin-top: 14px;
}

.application-type-nav{
  border: 1px solid #dcdcdc;
  border-right: none;
  background: #fafafa;
}

.application-type-nav h3,
.application-type-content h3{
  font-size: 18px;
  margin: 0 0 16px;
  font-weight: 700;
}

.application-type-nav h3{
  padding: 18px 18px 12px;
}

.application-type-nav ul{
  list-style: none;
  margin: 0;
  padding: 0;
}

.application-type-nav li{
  border-top: 1px solid #dcdcdc;
  padding: 14px 16px;
  font-weight: 700;
  color: #222;
  cursor: pointer;
  background: #fafafa;
  position: relative;
}

.application-type-nav li:hover{
  background: #f4f4f4;
}

.application-type-nav li.active{
  background: #fff;
  margin-right: -1px;
  z-index: 2;
}

.application-type-nav li.active::after{
  content: '';
  position: absolute;
  top: -1px;
  right: -1px;
  width: 2px;
  height: calc(100% + 2px);
  background: #fff;
}

.application-type-content{
  border: 1px solid #dcdcdc;
  background: #fff;
  padding: 24px 24px 22px;
  min-height: 100%;
}

.application-type-content .lead{
  margin: 0 0 22px;
  font-size: 18px;
  line-height: 1.5;
}

.diagram-card{
  margin-top: 18px;
  min-height: 170px;
  display: flex;
  align-items: center;
  justify-content: center;
}

@media (max-width: 900px){
  .application-type-shell{
    grid-template-columns: 1fr;
  }

  .application-type-nav{
    border-right: 1px solid #dcdcdc;
    border-bottom: none;
  }

  .application-type-nav li.active{
    margin-right: 0;
  }

  .application-type-nav li.active::after{
    display: none;
  }
}
</style>

<script>
(function(){
  const data = {
    'dual-targeting': {
      title: 'Dual-Targeting of Single Gene',
      lead: 'Increased editing efficiency through dual-targeting of single genes.',
      diagram: '<img src="/images/Untitled.png" alt="Illustration showing dual-targeting of a single gene." style="width:400px;"/>'
    },
    'interaction-mapping': {
      title: 'Genetic Interaction Mapping',
      lead: '',
      diagram: '<img src="/images/Untitled2.png" alt="Illustration showing genetic interaction mapping." style="width:400px;"/>'
    },
    'higher-order': {
      title: 'Higher-Order Multiplexing',
      lead: '',
      diagram: '<img src="/images/9f98469bd5f9b580f859.png" alt="Illustration showing higher-order multiplexing." style="width:400px;"/>'
    },
    'segment-deletion': {
      title: 'Genetic Segment Deletion',
      lead: '',
      diagram: '<img src="/images/14f936a036fbed5055a3.png" alt="Illustration showing genetic segment deletion." style="width:400px;"/>'
    },
    'element-deletion': {
      title: 'Genetic Element Deletion',
      lead: '',
      diagram: '<img src="/images/8e0017167c564dc4f263.png" alt="Illustration showing genetic element deletion." style="width:400px;"/>'
    },
    'ncrna-deletion': {
      title: 'Non-Coding RNA Deletion',
      lead: '',
      diagram: '<img src="/images/Untitled3.png" alt="Illustration showing non-coding rna deletion." style="width:400px;"/>'
    }
  };

  const list = document.getElementById('applicationTypeList');
  const title = document.getElementById('applicationTitle');
  const lead = document.getElementById('applicationLead');
  const diagram = document.getElementById('applicationDiagram');
  if(!list || !title || !lead || !diagram) return;

  function setActive(key){
    const payload = data[key] || data['dual-targeting'];
    title.textContent = payload.title;
    lead.textContent = payload.lead;
    diagram.innerHTML = payload.diagram;
    list.querySelectorAll('li').forEach(li => li.classList.toggle('active', li.dataset.key === key));
  }

  list.addEventListener('click', function(e){
    const li = e.target.closest('li[data-key]');
    if(!li) return;
    setActive(li.dataset.key);
  });

  list.addEventListener('keydown', function(e){
    const li = e.target.closest('li[data-key]');
    if(!li) return;
    if(e.key === 'Enter' || e.key === ' '){
      e.preventDefault();
      setActive(li.dataset.key);
    }
  });

  setActive('dual-targeting');
})();
</script>

<?php include __DIR__ . '/include/footer.php'; ?>
