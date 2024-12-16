<?php
namespace Juspay\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Juspay\Payment\Controller\Standard\JuspayPayment;

class StatusSync extends Action {
	protected $orderRepository;
	protected $juspayPayment;

	public function __construct(
		Context $context,
		OrderRepositoryInterface $orderRepository,
		JuspayPayment $juspayPayment
	) {
		parent::__construct( $context );
		$this->orderRepository = $orderRepository;
		$this->juspayPayment = $juspayPayment;
	}

	/**
	 * Execute action
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException|\Exception
	 */
	public function execute() {
		$orderId = $this->getRequest()->getParam( 'order_id' );
		$resultRedirect = $this->resultRedirectFactory->create();

		try {
			$order = $this->orderRepository->get( $orderId );
			$this->juspayPayment->manualStatusSync( $order );
			$this->messageManager->addSuccessMessage( __( 'Order Status Synced.' ) );
		} catch (\Exception $e) {
			$this->messageManager->addErrorMessage( $e->getMessage() );
		}

		return $resultRedirect->setPath( 'sales/order/view', [ 'order_id' => $order->getId() ] );
	}

	/**
	 * @return bool
	 */
	protected function _isAllowed() {
		return true;
	}
}