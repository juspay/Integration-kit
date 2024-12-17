<?php

namespace Juspay\Payment\Setup;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

class InstallData implements InstallDataInterface {

	const ORDER_STATE_PROCESSING_CODE = 'juspay_processing';
	const ORDER_STATUS_PROCESSING_CODE = 'juspay_processing';
	const ORDER_STATUS_PROCESSING_LABEL = 'Juspay Processing';
	const ORDER_STATE_REFUND_CODE = 'juspay_refunded';
	const ORDER_STATUS_REFUND_CODE = 'juspay_refunded';
	const ORDER_STATUS_REFUND_LABEL = 'Juspay Refunded';
	/**
	 * Status Factory
	 *
	 * @var StatusFactory
	 */
	protected $statusFactory;
	/**
	 * Status Resource Factory
	 *
	 * @var StatusResourceFactory
	 */
	protected $statusResourceFactory;
	/**
	 * InstallData constructor
	 *
	 * @param StatusFactory $statusFactory
	 * @param StatusResourceFactory $statusResourceFactory
	 */
	public function __construct(
		StatusFactory $statusFactory,
		StatusResourceFactory $statusResourceFactory
	) {
		$this->statusFactory = $statusFactory;
		$this->statusResourceFactory = $statusResourceFactory;
	}
	/**
	 * Installs data for a module
	 *
	 * @param ModuleDataSetupInterface $setup
	 * @param ModuleContextInterface $context
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function install( ModuleDataSetupInterface $setup, ModuleContextInterface $context ) {
		$this->addJuspayProcessingStatus();
		$this->addJuspayRefundStatus();
	}
	/**
	 * Create new order processing status and assign it to the existent state
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function addJuspayProcessingStatus() {
		/** @var StatusResource $statusResource */
		$statusResource = $this->statusResourceFactory->create();
		/** @var Status $status */
		$status = $this->statusFactory->create();
		$status->setData( [ 
			'status' => self::ORDER_STATUS_PROCESSING_CODE,
			'label' => self::ORDER_STATUS_PROCESSING_LABEL,
		] );
		try {
			$statusResource->save( $status );
		} catch (AlreadyExistsException $exception) {
			return;
		}
		$status->assignState( self::ORDER_STATE_PROCESSING_CODE, false, true );
	}
	/**
	 * Create new order refunded status and assign it to the new custom order state
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function addJuspayRefundStatus() {
		/** @var StatusResource $statusResource */
		$statusResource = $this->statusResourceFactory->create();
		/** @var Status $status */
		$status = $this->statusFactory->create();
		$status->setData( [ 
			'status' => self::ORDER_STATUS_REFUND_CODE,
			'label' => self::ORDER_STATUS_REFUND_LABEL,
		] );
		try {
			$statusResource->save( $status );
		} catch (AlreadyExistsException $exception) {
			return;
		}
		$status->assignState( self::ORDER_STATE_REFUND_CODE, true, true );
	}
}