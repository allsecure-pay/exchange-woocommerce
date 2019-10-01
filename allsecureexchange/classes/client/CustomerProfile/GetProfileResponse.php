<?php

namespace AllsecureExchange\Client\CustomerProfile;

use AllsecureExchange\Client\Json\ResponseObject;

/**
 * Class GetProfileResponse
 *
 * @package AllsecureExchange\Client\CustomerProfile
 *
 * @property bool $profileExists
 * @property string $profileGuid
 * @property string $customerIdentification
 * @property string $preferredMethod
 * @property CustomerData $customer
 * @property PaymentInstrument[] $paymentInstruments
 */
class GetProfileResponse extends ResponseObject {

}
