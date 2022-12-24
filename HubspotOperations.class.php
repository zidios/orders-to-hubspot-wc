<?php
class HubspotOperations {
	private $hubspot;
	function __construct(){

	}

	private function connect(){
		$options = get_option( 'wcoth_options' );
		if(!empty($options['wcoth_access_token'])){
			$this->hubspot = \HubSpot\Factory::createWithAccessToken($options['wcoth_access_token']);
		}
		return !empty($this->hubspot) ? true : false;
	}

	public function get_connected(){
		$result = true;
		if(empty($this->hubspot)){
			if(!$this->connect()) $result = false;
		}
		return $result;
	}

	private function search_contact($email){
		$result = false;
		if($this->get_connected()){
			$filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
			$filter
				->setOperator('EQ')
				->setPropertyName('email')
				->setValue($email);

			$filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
			$filterGroup->setFilters([$filter]);

			$searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
			$searchRequest->setFilterGroups([$filterGroup]);

			// Get specific properties
			$searchRequest->setProperties(['firstname', 'lastname', 'date_of_birth', 'email']);

			// @var CollectionResponseWithTotalSimplePublicObject $contactsPage
			$result = $this->hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest)->jsonSerialize();
		}
		return $result;
	}

	private function create_contact($email, $firstname = '', $lastname='', $phone='', $company=''){
		$result = false;
		if(!empty($email) && $this->get_connected()){
			$contactInput = new \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput();
			$properties = array('email' => $email);
			if(!empty($firstname)) $properties["firstname"] = $firstname;
			if(!empty($lastname)) $properties["lastname"] = $lastname;
			if(!empty($phone)) $properties["phone"] = $phone;
			if(!empty($company)) $properties["company"] = $company;
			$contactInput->setProperties($properties);
			$result = $this->hubspot->crm()->contacts()->basicApi()->create($contactInput)->jsonSerialize();
		}
		return $result;
	}

	/**
	 * @param string $email
	 * @param string $firstname
	 * @param string $lastname
	 * @param string $phone
	 * @param string $company
	 *
	 * @return false|int contact_id
	 */
	public function search_or_create_contact($email, $firstname = '', $lastname='', $phone='', $company=''){
		$result = false;
		if(!empty($email) && $this->get_connected()){
			$contactPage = $this->search_contact($email);
			if(!empty($contactPage) && !empty($contactPage->total) && !empty($contactPage->results[0]->id)){
				$result = intval($contactPage->results[0]->id);
			} else {
				$contact = $this->create_contact($email, $firstname, $lastname, $phone, $company);
				if(!empty($contact) && !empty($contact->id)){
					$result = intval($contact->id);
				}
			}
		}
		return $result;
	}
	public function create_deal($dealname, $amount = 0){
		$result = 0;
		if(!empty($dealname) && $this->get_connected()){
			$dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();
				$properties = array('dealname' => $dealname);
				$properties["amount"] = $amount;
				$dealInput->setProperties($properties);
				$result = $this->hubspot->crm()->deals()->basicApi()->create($dealInput)->jsonSerialize();
				$result = (!empty($result) && !empty($result->id)) ? intval($result->id) : 0;
		}
		return $result ;
	}

	public function create_line_item($product_name, $product_id, $product_quantity, $product_price){
		$lineItemId = 0;
		if($this->get_connected()){
			$lineItemInput = new \HubSpot\Client\Crm\LineItems\Model\SimplePublicObjectInput();
			$properties = array(
				"name"=> $product_name,
				"quantity"=> intval($product_quantity),
				"price"=> $product_price
			);
			$lineItemInput->setProperties($properties);
			$result = $this->hubspot->crm()->lineItems()->basicApi()->create($lineItemInput)->jsonSerialize();
			$lineItemId = $result->id;
			$lineItemId = intval($lineItemId);
		}
		return $lineItemId;
	}
	public function associate_deal($dealId, $association_type_id, $associateObjectType, $associateObjectId){
		$result = false;
		if($this->get_connected()){
				if(!empty($dealId) && !empty($association_type_id) && !empty($associateObjectType) && !empty($associateObjectId) && $dealId > 0){
					$dealAssoc = new HubSpot\Client\Crm\Deals\Model\AssociationSpec(
						array('association_category' => 'HUBSPOT_DEFINED',
						      'association_type_id'  => $association_type_id)
					);
					$this->hubspot->crm()->deals()->associationsApi()->create( $dealId, $associateObjectType, $associateObjectId, [ $dealAssoc ] );
					$result = true;
				}

		}
		return $result;
	}
}