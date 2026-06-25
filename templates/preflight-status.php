<?php
/**
 * Preflight status template — rendered on Thank You page and My Account order view.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="ppp-bpe-preflight-section" class="ppp-bpe-preflight">
	<h2 class="ppp-bpe-preflight__title" id="ppp-bpe-preflight-title"></h2>
	<p class="ppp-bpe-preflight__description" id="ppp-bpe-preflight-description"></p>

	<div class="ppp-bpe-preflight__status" id="ppp-bpe-preflight-status"></div>
	<p class="ppp-bpe-preflight__message" id="ppp-bpe-preflight-message" style="display:none;"></p>

	<div class="ppp-bpe-preflight__report" id="ppp-bpe-preflight-report" style="display:none;">
		<h3 class="ppp-bpe-preflight__report-title" id="ppp-bpe-preflight-report-title"></h3>
		<div class="ppp-bpe-preflight__checks" id="ppp-bpe-preflight-checks"></div>
		<p class="ppp-bpe-preflight__summary" id="ppp-bpe-preflight-summary" style="display:none;"></p>
	</div>

	<div class="ppp-bpe-preflight__error" id="ppp-bpe-preflight-error" style="display:none;"></div>

	<div class="ppp-bpe-preflight__actions" id="ppp-bpe-preflight-actions">
		<button type="button" class="ppp-bpe-preflight__button" id="ppp-bpe-preflight-start"></button>
	</div>
</div>
