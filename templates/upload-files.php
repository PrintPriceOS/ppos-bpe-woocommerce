<?php
/**
 * Upload files template — rendered on Thank You page and My Account order view.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="ppp-bpe-upload-section" class="ppp-bpe-upload">
	<h2 class="ppp-bpe-upload__title" id="ppp-bpe-upload-title"></h2>
	<p class="ppp-bpe-upload__description" id="ppp-bpe-upload-description"></p>

	<div class="ppp-bpe-upload__status" id="ppp-bpe-upload-status"></div>

	<form class="ppp-bpe-upload__form" id="ppp-bpe-upload-form" enctype="multipart/form-data" novalidate>
		<div class="ppp-bpe-upload__fields">
			<div class="ppp-bpe-upload__field" id="ppp-bpe-upload-interior">
				<label class="ppp-bpe-upload__label" id="ppp-bpe-interior-label"></label>
				<div class="ppp-bpe-upload__dropzone" id="ppp-bpe-interior-dropzone" data-field="interior_pdf">
					<div class="ppp-bpe-upload__dropzone-content">
						<span class="ppp-bpe-upload__dropzone-icon">&#128196;</span>
						<span class="ppp-bpe-upload__dropzone-text" id="ppp-bpe-interior-droptext"></span>
						<span class="ppp-bpe-upload__dropzone-info" id="ppp-bpe-interior-info"></span>
					</div>
					<input type="file" name="interior_pdf" accept="application/pdf,.pdf" class="ppp-bpe-upload__input" id="ppp-bpe-interior-input" />
				</div>
				<div class="ppp-bpe-upload__file-info" id="ppp-bpe-interior-file-info" style="display:none;"></div>
			</div>

			<div class="ppp-bpe-upload__field" id="ppp-bpe-upload-cover">
				<label class="ppp-bpe-upload__label" id="ppp-bpe-cover-label"></label>
				<div class="ppp-bpe-upload__dropzone" id="ppp-bpe-cover-dropzone" data-field="cover_pdf">
					<div class="ppp-bpe-upload__dropzone-content">
						<span class="ppp-bpe-upload__dropzone-icon">&#128196;</span>
						<span class="ppp-bpe-upload__dropzone-text" id="ppp-bpe-cover-droptext"></span>
						<span class="ppp-bpe-upload__dropzone-info" id="ppp-bpe-cover-info"></span>
					</div>
					<input type="file" name="cover_pdf" accept="application/pdf,.pdf" class="ppp-bpe-upload__input" id="ppp-bpe-cover-input" />
				</div>
				<div class="ppp-bpe-upload__file-info" id="ppp-bpe-cover-file-info" style="display:none;"></div>
			</div>
		</div>

		<div class="ppp-bpe-upload__error" id="ppp-bpe-upload-error" style="display:none;"></div>
		<div class="ppp-bpe-upload__success" id="ppp-bpe-upload-success" style="display:none;"></div>

		<button type="submit" class="ppp-bpe-upload__submit" id="ppp-bpe-upload-submit" disabled></button>
	</form>
</div>
