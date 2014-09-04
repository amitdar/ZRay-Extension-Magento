<?php


class Magento {
	
	/**
	 * @var array
	 */
	private $eventTargets = array();
	
	/**
	 * @param array $context
	 * @param array $storage
	 */
	public function mageRunExit($context, &$storage){
		$storage['observers'] = array();
		$this->storeObservers($storage['observers']);

//		$storage['info'] = array(
/// store, website, frontcontroller all cause problems in unserialize
// 				'store' => Mage::app()->getStore(),
// 				'website' => Mage::app()->getWebsite(),
// 				'frontcontroller' => Mage::app()->getFrontController(),
//				'request' => Mage::app()->getRequest()
//		);

		foreach((array)Mage::app()->getRequest() as $key=>$value) {
		 $storage['request'][] = array('property' => $key, 'value' => $value);
		}

		$storage['handles'] = array_map(function($handle){
			return array('name' => $handle);
		}, Mage::app()->getLayout()->getUpdate()->getHandles());
	}
	
	
	/**
	 * @param array $context
	 */
	public function magDispatchEvent($context) {
		/// collect event targets for events collector
		$event = $context['functionArgs'][0];
		$args = $context['functionArgs'][1];
		$key = array_shift(array_intersect(array('object', 'resource', 'collection', 'front', 'controller_action'), array_keys($args)));
		$this->eventTargets[$event] = $args[$key];
	}
	
	/**
	 * @param array $context
	 * @param array $storage
	 */
	public function appCallObserverMethod($context, & $storage){

		$method = $context['functionArgs'][1];
		$observerData = $context['functionArgs'][2]->getData();
		$eventArgs = $observerData['event']->getData();
		$event = $observerData['event']->getName();
		$block = $observerData['event']->getBlock();
		$object = get_class($context['functionArgs'][0]);

		$storage['events'][] = array('event' => $event, 'class' => $object, 'method' => $method,
				'duration' => $context['durationInclusive'], 'block' => $block, 'target' => get_class($this->eventTargets[$event]));
	}
	
	/**
	 * @param array $storage
	 */
	private function storeObservers(& $storage) {
		foreach (array('global', 'adminhtml', 'frontend') as $eventArea) {
			$eventConfig = $this->getEventAreaEventConfigs($eventArea);
			if (! ($eventConfig instanceof Mage_Core_Model_Config_Element)) {
				continue;
			}
			
			$events = $eventConfig->children();
			$this->processEventObservers($events, $eventArea, $storage);
		}
	}
	
	/**
	 * @param string $eventArea
	 * @return Mage_Core_Model_Config_Element|null
	 */
	private function getEventAreaEventConfigs($eventArea) {
		return Mage::app()->getConfig()->getNode(sprintf('%s/events', $eventArea));
	}
	
	/**
	 * @param array $areaEvents
	 * @param string $eventArea
	 * @param array $storage
	 */
	private function processEventObservers($areaEvents, $eventArea, & $storage) {
		foreach ($areaEvents as $eventName => $event) {
			foreach ($event->observers->children() as $observerName => $observer) {
				$observerData = array(
						'area' => $eventArea,
						'event' => $eventName,
						'name' => $observerName,
                        'type' => (string)$observer->type ? (string)$observer->type : 'singleton',
						'class' => Mage::app()->getConfig()->getModelClassName($observer->class),
						'method' => (string)$observer->method
				);
				$storage[] = $observerData;
			}
		}
	}
}


$zrayMagento = new Magento();

$zre = new ZRayExtension('magento');

$zre->setMetadata(array('assets' => array(
		'viewscript' => array('filepath' => __DIR__ . '/magento.phtml', 'mime' => 'text/html'),
)));

$zre->traceFunction('Mage::run', function(){}, array($zrayMagento, 'mageRunExit'));
$zre->traceFunction('Mage_Core_Model_App::_callObserverMethod', function(){}, array($zrayMagento, 'appCallObserverMethod'));
$zre->traceFunction('Mage::dispatchEvent', array($zrayMagento, 'magDispatchEvent'), function(){});
//$zre->traceFunction('Mage_Core_Controller_Varien_Front', function(){}, function(){});
////$this->_getRequestRewriteController()->rewrite();
