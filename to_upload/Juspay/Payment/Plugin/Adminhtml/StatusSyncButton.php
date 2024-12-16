<?php
namespace Juspay\Payment\Plugin\Adminhtml;

use Magento\Sales\Api\OrderRepositoryInterface;

class StatusSyncButton {
	protected $orderRepository;

	public function __construct(
		OrderRepositoryInterface $orderRepository,
	) {
		$this->orderRepository = $orderRepository;
	}

	/**
	 * @param \Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject
	 * @param \Magento\Framework\View\Element\AbstractBlock $context
	 * @param \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
	 */
	public function beforePushButtons(
		\Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject,
		\Magento\Framework\View\Element\AbstractBlock $context,
		\Magento\Backend\Block\Widget\Button\ButtonList $buttonList
	) {
		if ( $context->getRequest()->getFullActionName() == 'sales_order_view' ) {
			$orderId = $context->getRequest()->getParam( 'order_id' );

			$order = $this->orderRepository->get( $orderId );
			$orderStatus = $order->getStatus();

			if ( $orderStatus == 'pending_payment' ) {
				try {
					$url = $context->getUrl( 'juspay_payment/order/statussync', [ 'order_id' => $orderId ] );
					$buttonList->add(
						'customButton',
						[ 'label' => __( 'Sync Status' ), 'onclick' => 'setLocation("' . $url . '")', 'class' => 'manual-sync' ],
						-1
					);
				} catch (\Exception $e) {
					$order->addCommentToStatusHistory( "Couldn't add Manual Status Sync button. Error: " . $e->getMessage(), false );
				}
				$order->save();
			}
		}
	}
}