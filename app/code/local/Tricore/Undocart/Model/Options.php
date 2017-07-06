<?php
class Tricore_Undocart_Model_Options
{
  /**
   * Provide available options as a value/label array
   *
   * @return array
   */
  public function toOptionArray()
  {
    return array(
      array('value'=>'0', 'label'=>'Enable'),
      array('value'=>'1', 'label'=>'Disable')                
    );
  }
  
}
