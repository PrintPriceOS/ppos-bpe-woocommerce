<?php
/**
 * Calculator form template.
 *
 * Variables available: $form_options, $atts, $default_country.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

$wrapper_class = 'ppp-bpe-calculator';
if ( 'compact' === ( $atts['mode'] ?? 'full' ) ) {
	$wrapper_class .= ' ppp-bpe-calculator--compact';
}
?>
<div id="printpricepro-bpe-calculator" class="<?php echo esc_attr( $wrapper_class ); ?>">
	<form class="ppp-bpe-calculator__form" id="ppp-bpe-calc-form" novalidate>

		<div class="ppp-bpe-calculator__section">
			<h3 class="ppp-bpe-calculator__heading"><?php esc_html_e( 'Book Specifications', 'printpricepro-bpe' ); ?></h3>

			<div class="ppp-bpe-calculator__field">
				<label for="ppp-bpe-book-size"><?php esc_html_e( 'Book Size', 'printpricepro-bpe' ); ?></label>
				<select id="ppp-bpe-book-size" name="book_size" required>
					<?php foreach ( $form_options['book_sizes'] as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ppp-bpe-calculator__field">
				<label for="ppp-bpe-pages"><?php esc_html_e( 'Pages', 'printpricepro-bpe' ); ?></label>
				<input type="number" id="ppp-bpe-pages" name="pages" min="8" max="1000" step="2" value="100" required />
			</div>

			<div class="ppp-bpe-calculator__field">
				<label for="ppp-bpe-copies"><?php esc_html_e( 'Copies', 'printpricepro-bpe' ); ?></label>
				<input type="number" id="ppp-bpe-copies" name="copies" min="1" max="10000" value="<?php echo esc_attr( absint( $atts['default_copies'] ) ); ?>" required />
			</div>
		</div>

		<div class="ppp-bpe-calculator__section">
			<h3 class="ppp-bpe-calculator__heading"><?php esc_html_e( 'Print Options', 'printpricepro-bpe' ); ?></h3>

			<div class="ppp-bpe-calculator__field">
				<span class="ppp-bpe-calculator__label"><?php esc_html_e( 'Interior Color', 'printpricepro-bpe' ); ?></span>
				<div class="ppp-bpe-calculator__radio-group">
					<?php foreach ( $form_options['interior_colors'] as $key => $label ) : ?>
						<label>
							<input type="radio" name="interior_color" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, 'bw' ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ppp-bpe-calculator__field">
				<span class="ppp-bpe-calculator__label"><?php esc_html_e( 'Cover Color', 'printpricepro-bpe' ); ?></span>
				<div class="ppp-bpe-calculator__radio-group">
					<?php foreach ( $form_options['cover_colors'] as $key => $label ) : ?>
						<label>
							<input type="radio" name="cover_color" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, 'color' ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ppp-bpe-calculator__field">
				<label for="ppp-bpe-binding"><?php esc_html_e( 'Binding', 'printpricepro-bpe' ); ?></label>
				<select id="ppp-bpe-binding" name="binding" required>
					<?php foreach ( $form_options['binding_types'] as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ppp-bpe-calculator__field">
				<label for="ppp-bpe-paper"><?php esc_html_e( 'Paper', 'printpricepro-bpe' ); ?></label>
				<select id="ppp-bpe-paper" name="paper" required>
					<?php foreach ( $form_options['paper_types'] as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="ppp-bpe-calculator__section ppp-bpe-calculator__section--full">
			<div class="ppp-bpe-calculator__field">
				<label for="ppp-bpe-country"><?php esc_html_e( 'Country', 'printpricepro-bpe' ); ?></label>
				<select id="ppp-bpe-country" name="country">
					<?php foreach ( $form_options['countries'] as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $default_country ); ?>>
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<input type="hidden" name="product_type" value="<?php echo esc_attr( $atts['product_type'] ); ?>" />

			<button type="submit" class="ppp-bpe-calculator__submit" id="ppp-bpe-calc-submit">
				<?php esc_html_e( 'Calculate Price', 'printpricepro-bpe' ); ?>
			</button>
		</div>

	</form>

	<div class="ppp-bpe-calculator__loading" id="ppp-bpe-calc-loading" style="display:none;">
		<?php esc_html_e( 'Calculating…', 'printpricepro-bpe' ); ?>
	</div>

	<div class="ppp-bpe-calculator__error" id="ppp-bpe-calc-error" style="display:none;"></div>

	<div class="ppp-bpe-calculator__results" id="ppp-bpe-calc-results" style="display:none;">
		<h3 class="ppp-bpe-calculator__heading"><?php esc_html_e( 'Price Estimate', 'printpricepro-bpe' ); ?></h3>

		<div class="ppp-bpe-calculator__summary" id="ppp-bpe-calc-summary"></div>

		<table class="ppp-bpe-calculator__breakdown" id="ppp-bpe-calc-breakdown">
			<tbody></tbody>
		</table>

		<div class="ppp-bpe-calculator__total" id="ppp-bpe-calc-total"></div>

		<button type="button" class="ppp-bpe-calculator__add-to-cart" id="ppp-bpe-add-to-cart" disabled>
			<?php esc_html_e( 'Add to Cart', 'printpricepro-bpe' ); ?>
		</button>
		<div class="ppp-bpe-calculator__cart-message" id="ppp-bpe-cart-message" style="display:none;"></div>
	</div>
</div>
