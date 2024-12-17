<?php
namespace Juspay\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;

class MagentoVersionCheck implements ObserverInterface {
	protected $productMetadata;
	protected $configWriter;

	public function __construct(
		ProductMetadataInterface $productMetadata,
		WriterInterface $configWriter
	) {
		$this->productMetadata = $productMetadata;
		$this->configWriter = $configWriter;
	}

	public function execute( Observer $observer ) {
		$magentoVersion = $this->productMetadata->getVersion();

		// the minimum Magento version our module supports
		$minVersion = '2.3.0';

		if ( version_compare( $magentoVersion, $minVersion, '>=' ) ) {
			$this->configWriter->save( 'payment/juspay/active', 1 );
		} else {
			$this->configWriter->save( 'payment/juspay/active', 0 );
			throw new LocalizedException(
				__( 'This module is not compatible with Magento version %1. Compatible with versions above %2', $magentoVersion, $minVersion )
			);
		}
	}
}
