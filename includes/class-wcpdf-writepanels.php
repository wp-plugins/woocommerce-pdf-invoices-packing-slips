<?php

/**
 * Writepanel class
 */
if ( !class_exists( 'WooCommerce_PDF_Invoices_Writepanels' ) ) {

	class WooCommerce_PDF_Invoices_Writepanels {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'woocommerce_admin_order_actions_end', array( $this, 'add_listing_actions' ) );
			add_action( 'add_meta_boxes_shop_order', array( $this, 'add_box' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_account_pdf_link' ), 10, 2 );
			add_action( 'admin_print_scripts', array( $this, 'add_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'add_styles' ) );
			add_action( 'admin_footer-edit.php', array(&$this, 'bulk_actions') );
			$this->general_settings = get_option('wpo_wcpdf_general_settings');
			$this->template_settings = get_option('wpo_wcpdf_template_settings');
		}

		/**
		 * Add the styles
		 */
		public function add_styles() {
			if( $this->is_order_edit_page() ) {
				wp_enqueue_style( 'thickbox' );
				wp_enqueue_style( 'wpo-wcpdf', WooCommerce_PDF_Invoices::$plugin_url . 'css/style.css' );
			}
		}
		
		/**
		 * Add the scripts
		 */
		public function add_scripts() {
			if( $this->is_order_edit_page() ) {
				wp_enqueue_script( 'wpo-wcpdf', WooCommerce_PDF_Invoices::$plugin_url . 'js/script.js', array( 'jquery' ) );
				wp_localize_script(  
				    'wpo-wcpdf',  
				    'wpo_wcpdf_ajax',  
				    array(  
				        'ajaxurl' => admin_url( 'admin-ajax.php' ), // URL to WordPress ajax handling page  
				        'nonce' => wp_create_nonce('generate_wpo_wcpdf')  
				    )  
				);  
			}
		}	
			
		/**
		 * Is order page
		 */
		public function is_order_edit_page() {
			global $post_type;
			if( $post_type == 'shop_order' ) {
				return true;	
			} else {
				return false;
			}
		}	
			
		/**
		 * Add PDF actions to the orders listing
		 */
		public function add_listing_actions( $order ) {
			?>
			<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $order->id ), 'generate_wpo_wcpdf' ); ?>" class="button tips wpo_wcpdf" target="_blank" alt="<?php esc_attr_e( 'PDF invoice', 'wpo_wcpdf' ); ?>" data-tip="<?php esc_attr_e( 'PDF invoice', 'wpo_wcpdf' ); ?>">
				<img src="<?php echo WooCommerce_PDF_Invoices::$plugin_url . 'images/invoice.png'; ?>" alt="<?php esc_attr_e( 'PDF invoice', 'wpo_wcpdf' ); ?>" width="16">
			</a>
			<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=packing-slip&order_ids=' . $order->id ), 'generate_wpo_wcpdf' ); ?>" class="button tips wpo_wcpdf" target="_blank" alt="<?php esc_attr_e( 'PDF Packing Slip', 'wpo_wcpdf' ); ?>" data-tip="<?php esc_attr_e( 'PDF Packing Slip', 'wpo_wcpdf' ); ?>">
				<img src="<?php echo WooCommerce_PDF_Invoices::$plugin_url . 'images/packing-slip.png'; ?>" alt="<?php esc_attr_e( 'PDF Packing Slip', 'wpo_wcpdf' ); ?>" width="16">
			</a>
			<?php
		}
		
		/**
		 * Add the meta box on the single order page
		 */
		public function add_box() {
			add_meta_box( 'wpo_wcpdf-box', __( 'Create PDF', 'wpo_wcpdf' ), array( $this, 'create_box_content' ), 'shop_order', 'side', 'default' );
		}

		public function my_account_pdf_link( $actions, $order ) {
			$pdf_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $order->id . '&my-account'), 'generate_wpo_wcpdf' );

			$actions['invoice'] = array(
				'url'  => $pdf_url,
				'name' => __( 'Download invoice (PDF)', 'wpo_wcpdf' )
			);

			return $actions;
		}

		/**
		 * Create the meta box content on the single order page
		 */
		public function create_box_content() {
			global $post_id;
			?>
			<ul class="wpo_wcpdf-actions">
				<li><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $post_id ), 'generate_wpo_wcpdf' ); ?>" class="button" target="_blank" alt="<?php esc_attr_e( 'PDF Invoice', 'wpo_wcpdf' ); ?>"><?php _e( 'PDF invoice', 'wpo_wcpdf' ); ?></a></li>
				<li><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=packing-slip&order_ids=' . $post_id ), 'generate_wpo_wcpdf' ); ?>" class="button" target="_blank" alt="<?php esc_attr_e( 'PDF Packing Slip', 'wpo_wcpdf' ); ?>"><?php _e( 'PDF Packing Slip', 'wpo_wcpdf' ); ?></a></li>
			</ul>
			<?php
		}
		/**
		 * Add actions to menu
		 */
		public function bulk_actions() {
			global $post_type;
	
			if ( 'shop_order' == $post_type ) {
				?>
				<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('<option>').val('invoice').text('<?php _e( 'PDF Invoices', 'wpo_wcpdf' )?>').appendTo("select[name='action']");
					jQuery('<option>').val('invoice').text('<?php _e( 'PDF Invoices', 'wpo_wcpdf' )?>').appendTo("select[name='action2']");
					jQuery('<option>').val('packing-slip').text('<?php _e( 'PDF Packing Slips', 'wpo_wcpdf' )?>').appendTo("select[name='action']");
					jQuery('<option>').val('packing-slip').text('<?php _e( 'PDF Packing Slips', 'wpo_wcpdf' )?>').appendTo("select[name='action2']");
				});
				</script>
				<?php
			}
		}				
	}
}