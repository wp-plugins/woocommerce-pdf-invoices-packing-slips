<?php

/**
 * PDF Export class
 */
if ( ! class_exists( 'WooCommerce_PDF_Invoices_Export' ) ) {

	class WooCommerce_PDF_Invoices_Export {

		public $template_directory_name;
		public $template_base_path;
		public $template_default_base_path;
		public $template_default_base_uri;
		public $template_path;

		public $order;
		public $template_type;
		public $order_id;
		public $output_body;

		/**
		 * Constructor
		 */
		public function __construct() {					
			global $woocommerce;
			$this->order = new WC_Order();
			$this->general_settings = get_option('wpo_wcpdf_general_settings');
			$this->template_settings = get_option('wpo_wcpdf_template_settings');

			$this->template_directory_name = 'pdf';
			$this->template_base_path = (defined('WC_TEMPLATE_PATH')?WC_TEMPLATE_PATH:$woocommerce->template_url) . $this->template_directory_name . '/';
			$this->template_default_base_path = WooCommerce_PDF_Invoices::$plugin_path . 'templates/' . $this->template_directory_name . '/';
			$this->template_default_base_uri = WooCommerce_PDF_Invoices::$plugin_url . 'templates/' . $this->template_directory_name . '/';

			$this->template_path = $this->template_settings['template_path'];

			add_action( 'wp_ajax_generate_wpo_wcpdf', array($this, 'generate_pdf_ajax' ));
			add_filter( 'woocommerce_email_attachments', array( $this, 'attach_pdf_to_email' ), 99, 3);
		}
		
		/**
		 * Generate the template output
		 */
		public function process_template( $template_type, $order_ids ) {
			$this->template_type = $template_type;
			$this->order_ids = $order_ids;

			$output_html = array();
			foreach ($order_ids as $order_id) {
				$this->add_invoice_number( $order_id );
				$this->order = new WC_Order( $order_id );
				$template = $this->template_path . '/' . $template_type . '.php';

				if (!file_exists($template)) {
					die('Template not found! Check if the following file exists: <pre>'.$template.'</pre><br/>');
				}

				$output_html[$order_id] = $this->get_template($template);

				// Wipe post from cache
				wp_cache_delete( $order_id, 'posts' );
				wp_cache_delete( $order_id, 'post_meta' );
			}

			// Try to clean up a bit of memory
			unset($this->order);

			$print_script = "<script language=javascript>window.onload = function(){ window.print(); };</script>";
			$page_break = "\n<div style=\"page-break-before: always;\"></div>\n";


			if (apply_filters('wpo_wcpdf_output_html', false, $template_type) && apply_filters('wpo_wcpdf_print_html', false, $template_type)) {
				$this->output_body = $print_script . implode($page_break, $output_html);
			} else {
				$this->output_body = implode($page_break, $output_html);
			}

			// Try to clean up a bit of memory
			unset($output_html);

			$template_wrapper = $this->template_path . '/html-document-wrapper.php';

			if (!file_exists($template_wrapper)) {
				die('Template wrapper not found! Check if the following file exists: <pre>'.$template_wrapper.'</pre><br/>');
			}		

			$complete_document = $this->get_template($template_wrapper);

			// Try to clean up a bit of memory
			unset($this->output_body);
			
			// clean up special characters
			$complete_document = utf8_decode(mb_convert_encoding($complete_document, 'HTML-ENTITIES', 'UTF-8'));

			return $complete_document;
		}

		/**
		 * Create & render DOMPDF object
		 */
		public function generate_pdf( $template_type, $order_ids )	{
			require_once( WooCommerce_PDF_Invoices::$plugin_path . "lib/dompdf/dompdf_config.inc.php" );  
			$dompdf = new DOMPDF();
			$dompdf->load_html($this->process_template( $template_type, $order_ids ));
			$dompdf->set_paper($this->template_settings['paper_size'], 'portrait');
			$dompdf->render();

			// Try to clean up a bit of memory
			unset($complete_pdf);

			return $dompdf;
		}

		/**
		 * Stream PDF
		 */
		public function stream_pdf( $template_type, $order_ids, $filename ) {
			$pdf = $this->generate_pdf( $template_type, $order_ids );
			$pdf->stream($filename);
		}
		
		/**
		 * Get PDF
		 */
		public function get_pdf( $template_type, $order_ids ) {
			$pdf = $this->generate_pdf( $template_type, $order_ids );
			return $pdf->output();
		}

		/**
		 * Load and generate the template output with ajax
		 */
		public function generate_pdf_ajax() {
			// Check the nonce
			if( empty( $_GET['action'] ) || ! is_user_logged_in() || !check_admin_referer( $_GET['action'] ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wpo_wcpdf' ) );
			}
			
			// Check if all parameters are set
			if( empty( $_GET['template_type'] ) || empty( $_GET['order_ids'] ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wpo_wcpdf' ) );
			}

			// Check the user privileges
			if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) && !isset( $_GET['my-account'] ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wpo_wcpdf' ) );
			}

			$order_ids = (array) explode('x',$_GET['order_ids']);

			// User call from my-account page
			if ( isset( $_GET['my-account'] ) ) {
				// Only for single orders!
				if ( count( $order_ids ) > 1 ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'wpo_wcpdf' ) );
				}

				// Get user_id of order
				$order = new WC_Order ( $order_ids[0] );
				$order_user = $order->user_id;
				// Destroy object to save memory
				unset($order);
				// Get user_id of current user
				$user_id = get_current_user_id();	

				// Check if current user is owner of order IMPORTANT!!!
				if ( $order_user != $user_id ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'wpo_wcpdf' ) );
				}

				// if we got here, we're safe to go!
			}

			$template_type = $_GET['template_type'];
			if ($template_type == 'invoice' ) {
				$template_name = _n( 'invoice', 'invoices', count($order_ids), 'wpo_wcpdf' );
			} else {
				$template_name = _n( 'packing-slip', 'packing-slips', count($order_ids), 'wpo_wcpdf' );
			}

			// Filename
			if ( count($order_ids) > 1 ) {
				$filename = $template_name . '-' . date('Y-m-d') . '.pdf'; // 'invoices-2020-11-11.pdf'
			} else {
				$order = new WC_Order ( $order_ids[0] );
				$order_number = ltrim( $order->get_order_number(), '#' );
				$filename = $template_name . '-' . $order_number . '.pdf'; // 'packing-slip-123456.pdf'
			}
			$filename = apply_filters( 'wpo_wcpdf_bulk_filename', $filename, $order_ids, $template_name );
			
			// Generate the output
			// $this->stream_pdf( $template_type, $order_ids, $filename );

			if (apply_filters('wpo_wcpdf_output_html', false, $template_type)) {
				// Output html to browser for debug
				// NOTE! images will be loaded with the server path by default
				// use the wpo_wcpdf_use_path filter (return false) to change this to http urls
				die($this->process_template( $template_type, $order_ids ));
			}
		
			$invoice = $this->get_pdf( $template_type, $order_ids );

			// Get output setting
			$output_mode = isset($this->general_settings['download_display'])?$this->general_settings['download_display']:'';

			// Switch headers according to output setting
			if ( $output_mode == 'display' || empty($output_mode) ) {
				header('Content-type: application/pdf');
				header('Content-Disposition: inline; filename="'.$filename.'"');
			} else {
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename="'.$filename.'"'); 
				header('Content-Transfer-Encoding: binary');
				header('Connection: Keep-Alive');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
			}

			// output PDF data
			echo($invoice);

			exit;
		}

		/**
		 * Attach invoice to completed order or customer invoice email
		 */
		public function attach_pdf_to_email ( $attachments, $status , $order ) {
			if (!isset($this->general_settings['email_pdf']))
				return;

			// clear temp folder (from http://stackoverflow.com/a/13468943/1446634)
			$tmp_path = WooCommerce_PDF_Invoices::$plugin_path . 'tmp/';
			array_map('unlink', ( glob( $tmp_path.'*' ) ? glob( $tmp_path.'*' ) : array() ) );
			
			$order_status = apply_filters( 'wpo_wcpdf_attach_to_status', 'completed' );

			if( isset( $status ) && ( $status=="customer_" . $order_status . "_order" || $status == "customer_invoice" ) ) {
				$order_number = ltrim( $order->get_order_number(), '#' );
				$pdf_filename_prefix = __( 'invoice', 'wpo_wcpdf' );
				$pdf_filename = $pdf_filename_prefix . '-' . $order_number . '.pdf';
				$pdf_filename = apply_filters( 'wpo_wcpdf_attachment_filename', $pdf_filename, $order_number );
				$pdf_path = $tmp_path . $pdf_filename;
				
				$invoice = $this->get_pdf( 'invoice', (array) $order->id );
				file_put_contents ( $pdf_path, $invoice );
				$attachments[] = $pdf_path;
			}

			return $attachments;
		}

		public function add_invoice_number( $order_id ) {
			// Based on code from WooCommerce Sequential Order Numbers
			global $wpdb;

			$invoice_number = get_post_meta( $order_id, '_wcpdf_invoice_number', true );

			// add invoive number if it doesn't exist
			if ( $invoice_number == "" ) {
				// attempt the query up to 3 times for a much higher success rate if it fails (due to Deadlock)	
				// this seems to me like the safest way to avoid order number clashes
				$success = false;
				for ( $i = 0; $i < 3 && ! $success; $i++ ) {
					// Get maximum invoice number in the DB
					$max_invoice_number = $wpdb->get_var( 'SELECT max(cast(meta_value as UNSIGNED)) from ' . $wpdb->postmeta . ' where meta_key="_wcpdf_invoice_number"' );

					if ($max_invoice_number == '') {
						$invoice_number = $order_id;
					} else {
						$invoice_number = $max_invoice_number+1;
					}

					$success = $wpdb->query( 'INSERT INTO ' . $wpdb->postmeta . ' (post_id,meta_key,meta_value) VALUES (' . $order_id . ',"_wcpdf_invoice_number", '.$invoice_number.')' );
				}
			}

			return $invoice_number;
		}

		/**
		 * Return evaluated template contents
		 */
		public function get_template( $file ) {
			ob_start();
		    if (file_exists($file)) {
				include($file);
			}
			return ob_get_clean();
		}			
		
		/**
		 * Get the current order
		 */
		public function get_order() {
			return $this->order;
		}

		/**
		 * Get the current order items
		 */
		public function get_order_items() {
			global $woocommerce;
			global $_product;

			$items = $this->order->get_items();
			$data_list = array();
		
			if( sizeof( $items ) > 0 ) {
				foreach ( $items as $item ) {
					// Array with data for the pdf template
					$data = array();
					
					// Set the id
					$data['product_id'] = $item['product_id'];
					$data['variation_id'] = $item['variation_id'];
										
					// Set item name
					$data['name'] = $item['name'];
					
					// Set item quantity
					$data['quantity'] = $item['qty'];

					// Set the subtotal for the number of products
					$data['line_total'] = $item['line_total'];
					$data['line_tax'] = $item['line_tax'];
					$data['tax_rates'] = $this->get_tax_rate( $item['tax_class'], $item['line_total'], $item['line_tax'] );
					
					// Set the final subtotal for all products
					$data['line_subtotal'] = $item['line_subtotal'];
					$data['line_subtotal_tax'] = $item['line_subtotal_tax'];
					$data['ex_price'] = $this->get_formatted_item_price ( $item, 'total', 'excl' );
					$data['price'] = $this->get_formatted_item_price ( $item, 'total' );

					// Calculate the single price with the same rules as the formatted line subtotal (!)
					$data['ex_single_price'] = $this->get_formatted_item_price ( $item, 'single', 'excl' );
					$data['single_price'] = $this->get_formatted_item_price ( $item, 'single' );
					
					// Set item meta and replace it when it is empty
					$meta = new WC_Order_Item_Meta( $item['item_meta'] );	
					$data['meta'] = $meta->display( false, true );

					// Pass complete item array
	                $data['item'] = $item;
					
					// Create the product to display more info
					$data['product'] = null;
					
					$product = $this->order->get_product_from_item( $item );
					
					// Checking fo existance, thanks to MDesigner0 
					if(!empty($product)) {
						// Set the thumbnail id
						$data['thumbnail_id'] = $this->get_thumbnail_id( $product->id );

						// Set the thumbnail server path
						$data['thumbnail_path'] = get_attached_file( $data['thumbnail_id'] );

						// Thumbnail (full img tag)
						if (apply_filters('wpo_wcpdf_use_path', true)) {
							// load img with server path by default
							$data['thumbnail'] = sprintf('<img width="90" height="90" src="%s" class="attachment-shop_thumbnail wp-post-image">', $data['thumbnail_path']);
						} else {
							// load img with http url when filtered
							$data['thumbnail'] = $product->get_image( 'shop_thumbnail', array( 'title' => '' ) );
						}
						
						// Set the single price (turned off to use more consistent calculated price)
						// $data['single_price'] = woocommerce_price ( $product->get_price() );
										
						// Set item SKU
						$data['sku'] = $product->get_sku();
		
						// Set item weight
						$data['weight'] = $product->get_weight();
						
						// Set item dimensions
						$data['dimensions'] = $product->get_dimensions();
					
						// Pass complete product object
						$data['product'] = $product;
					
					}

					$data_list[] = apply_filters( 'wpo_wcpdf_order_item_data', $data );
				}
			}

			return apply_filters( 'wpo_wcpdf_order_items_data', $data_list );
		}
		
		/**
		 * Gets price - formatted for display.
		 *
		 * @access public
		 * @param mixed $item
		 * @return string
		 */
		public function get_formatted_item_price ( $item, $type, $tax_display = '' ) {
			$item_price = 0;
			$divider = ($type == 'single')?$item['qty']:1; //divide by 1 if $type is not 'single' (thus 'total')

			if ( ! isset( $item['line_subtotal'] ) || ! isset( $item['line_subtotal_tax'] ) ) 
				return;

			if ( version_compare( WOOCOMMERCE_VERSION, '2.1' ) >= 0 ) {
				// WC 2.1 or newer is used
				if ( $tax_display == 'excl' ) {
					$item_price = wc_price( ($this->order->get_line_subtotal( $item )) / $divider, array( 'currency' => $this->order->get_order_currency() ) );
				} else {
					$item_price = wc_price( ($this->order->get_line_subtotal( $item, true )) / $divider, array('currency' => $this->order->get_order_currency()) );
				}
			} else {
				// Backwards compatibility
				if ( $tax_display == 'excl' ) {
					$item_price = woocommerce_price( ($this->order->get_line_subtotal( $item )) / $divider );
				} else {
					$item_price = woocommerce_price( ($this->order->get_line_subtotal( $item, true )) / $divider );
				}
			}


			return $item_price;
		}
		/**
		 * Get the tax rates/percentages for a given tax class
		 * @param  string $tax_class tax class slug
		 * @return string $tax_rates imploded list of tax rates
		 */
		public function get_tax_rate( $tax_class, $line_total, $line_tax ) {
			if (empty($tax_class))
				$tax_class = 'standard';

			if ( version_compare( WOOCOMMERCE_VERSION, '2.1' ) >= 0 ) {
				// WC 2.1 or newer is used
				$tax = new WC_Tax();
				$taxes = $tax->get_rates( $tax_class );

				foreach ($taxes as $tax) {
					$tax_rates[$tax['label']] = round( $tax['rate'], 2 ).'%';
				}

				if (empty($tax_rates))
					$tax_rates = (array) '-';

				$tax_rates = implode(' ,', $tax_rates );
			} else {
				// Backwards compatibility: calculate tax from line items
				if ( $line_total != 0) {
					$tax_rates = round( ($line_tax / $line_total)*100, 1 ).'%';
				} else {
					$tax_rates = '-';
				}
			}
			
			return $tax_rates;
		}

		/**
		 * Get order custom field
		 */
		public function get_order_field( $field ) {
			if( isset( $this->get_order()->order_custom_fields[$field] ) ) {
				return $this->get_order()->order_custom_fields[$field][0];
			} 
			return;
		}

	    /**
	     * Returns the main product image ID
		 * Adapted from the WC_Product class
	     *
	     * @access public
	     * @return string
	     */
	    public function get_thumbnail_id ( $product_id ) {
	    	global $woocommerce;
	
			if ( has_post_thumbnail( $product_id ) ) {
				$thumbnail_id = get_post_thumbnail_id ( $product_id );
			} elseif ( ( $parent_id = wp_get_post_parent_id( $product_id ) ) && has_post_thumbnail( $product_id ) ) {
				$thumbnail_id = get_post_thumbnail_id ( $parent_id );
			} else {
				$thumbnail_id = $woocommerce->plugin_url() . '/assets/images/placeholder.png';
			}
	
			return $thumbnail_id;
	    }
		
	}

}