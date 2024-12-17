<?php


namespace Juspay\Payment\Model\Config\Source;


class Integration implements \Magento\Framework\Option\ArrayInterface {
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return [ [ 'value' => 'redirection', 'label' => __( 'Redirection' ) ], [ 'value' => 'iframe', 'label' => __( 'Iframe' ) ] ];
	}

	/**
	 * Get options in "key-value" format
	 *
	 * @return array
	 */
	public function toArray() {
		return [ 'redirection' => __( 'Redirection' ), 'iframe' => __( 'Iframe' ) ];
	}
}
