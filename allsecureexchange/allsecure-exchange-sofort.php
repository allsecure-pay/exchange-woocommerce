<?php
class AllsecureExchange_sofort extends AllsecureExchange_Additional_Payment_Method_Abstract {
    public function __construct() {
        $method = get_class();
        $this->method = str_replace('AllsecureExchange_','', $method);
        $this->id = 'allsecureexchange_'.$this->method;
        $this->prefix = $this->id.'_';
        $this->title_default = ucwords($this->method);
        $this->method_title = __('AllSecure Exchange ', $this->domain). $this->title_default;
        
        parent::__construct();
    }
}
