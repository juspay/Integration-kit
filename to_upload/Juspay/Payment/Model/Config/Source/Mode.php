<?php


namespace Juspay\Payment\Model\Config\Source;


class Mode implements \Magento\Framework\Option\ArrayInterface {
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return [ [ 'value' => 'sandbox', 'label' => __( 'Sandbox' ) ], [ 'value' => 'production', 'label' => __( 'Production' ) ] ];
	}

	/**
	 * Get options in "key-value" format
	 *
	 * @return array
	 */
	public function toArray() {
		return [ 'sandbox' => __( 'Sandbox' ), 'production' => __( 'Production' ) ];
	}
}
