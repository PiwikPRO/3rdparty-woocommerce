<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<script type="text/javascript">
	var _paq = _paq || [];
	_paq.push(["trackPageView"]);
	_paq.push(["enableLinkTracking"]);
	(function () {
		var u = (("https:" == document.location.protocol) ? "https" : "http") + "://<?php echo esc_js($this->piwik_domain_name); ?>/";
		_paq.push(["setTrackerUrl", u + "piwik.php"]);
		_paq.push(["setSiteId", <?php echo esc_js($this->piwik_idsite); ?>]);
		var d = document, g = d.createElement("script"), s = d.getElementsByTagName("script")[0];
		g.type = "text/javascript";
		g.defer = true;
		g.async = true;
		g.src = u + "piwik.js";
		s.parentNode.insertBefore(g, s);
	})();
</script>
<noscript>
	<p>
		<img
			src="http://<?php echo esc_js( $this->piwik_domain_name ); ?>/piwik.php?idsite=<?php echo esc_js( $this->piwik_idsite ); ?>"
			style="border:0;" alt=""/>
	</p>
</noscript>