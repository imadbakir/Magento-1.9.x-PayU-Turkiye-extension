<?php

class Payu_PayuCheckout_Block_Shared_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $shared = $this->getOrder()->getPayment()->getMethodInstance();

        $form = new Varien_Data_Form();
        $form->setAction($shared->getPayuCheckoutSharedUrl())
            ->setId('payucheckout_shared_checkout')
            ->setName('payucheckout_shared_checkout')
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($shared->getFormFields() as $field=>$value) {
        	if(is_array($value)){
        		for($i=0;$i < sizeOf($value); $i++){
					$form->addField($field.'['.$i.']', 'hidden', array('name'=>$field.'[]', 'value'=>isset($value[$i]) ? $value[$i] : ''));
        		}
        	}else{
        		$form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        	}
        }

        $html = '<html><body>';
        $html.= $this->__('You will be redirected to PayuCheckout in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("payucheckout_shared_checkout").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }
}