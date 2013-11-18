<?php
class EDD_DCF_Product {
	public $id = 0;
	public $categories = '';
	public $price = 0;
	public function __construct( $product ) {
		$this->categories = wp_get_post_terms( $product, 'download_category', array(
			 'fields' => 'ids' 
		) );
		$this->price      = edd_price( $product, false );
	}
}