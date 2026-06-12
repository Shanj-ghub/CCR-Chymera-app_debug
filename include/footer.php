<?php
/* include/footer.php - updated footer to better match the NIH / CHyMErA reference layout */
?>
  </main> <!-- .content-wrap -->
</div> <!-- .page-wrap -->

<footer class="site-footer">
  <div class="container footer-inner">
    <div class="col footer-brand-col">
      <h4 class="ccrfooter">National Cancer Institute
        <span>At the National Institutes of Health</span>
      </h4>
      <br>
      <h4>Follow Us</h4>
      <div class="social" aria-label="Social media links">
        <a href="https://www.facebook.com/cancer.gov" title="Friend Us on Facebook" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-facebook"></i></a>
        <a href="https://twitter.com/NCIResearchCtr" title="Follow Us on Twitter" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-twitter"></i></a>
        <a href="https://www.youtube.com/user/NCIgov" title="Find Us on Youtube" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-youtube"></i></a>
      </div>
    </div>

    <div class="col footer-links-col">
      <h4>Organization</h4>
      <ul>
        <li><a href="http://www.hhs.gov">U.S. Department of Health and Human Services</a></li>
        <li><a href="http://www.nih.gov">National Institutes of Health</a></li>
        <li><a href="https://www.cancer.gov">National Cancer Institute</a></li>
        <li><a href="http://usa.gov">USA.gov</a></li>
      </ul>
    </div>

    <div class="col footer-links-col">
      <h4>More Information</h4>
      <ul>
        <li><a href="https://ccr.cancer.gov/about/contact">Contact Us</a></li>
        <li><a href="https://www.cancer.gov/policies/accessibility">Viewing Files</a></li>
      </ul>
    </div>

    <div class="col footer-links-col">
      <h4>Policies</h4>
      <ul>
        <li><a href="https://www.cancer.gov/policies">Policies</a></li>
        <li><a href="https://www.cancer.gov/policies/accessibility">Accessibility</a></li>
        <li><a href="https://www.cancer.gov/policies/disclaimer">Disclaimer</a></li>
        <li><a href="https://www.cancer.gov/policies/foia">FOIA</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container">
      <small>NIH... Turning Discovery Into Health®</small>
    </div>
  </div>
</footer>

<script src="/js/script.js"></script>
<script>
  // Warn on external link
  document.addEventListener('click', function (e) {
  const a = e.target.closest('a');
  if (!a) return;

  const href = a.getAttribute('href') || '';
  const host = window.location.hostname;

  const isInternal =
    href.startsWith('/')          ||
    href.startsWith('#')          ||
    href.startsWith('../')        ||
    href.startsWith('javascript') ||
    href.startsWith('mailto:')    ||
    href.startsWith(host)         ||
    href.startsWith('http://'  + host) ||
    href.startsWith('https://' + host) ||
    href.includes('.cancer.gov')  ||
    href.includes('.ncifcrf.gov');

  if (!isInternal && !window.confirm('You are now leaving this CCR sponsored resource.')) {
    e.preventDefault();
  }
});
</script>
</body>
</html>
