<?php

namespace App\Traits\Response;

use DOMDocument;
use Illuminate\Support\Carbon;
use App\Services\Phone\Nigeria as NigerianPhone;
use App\Services\Str;

trait Interswitch
{

    /**
     * Response code for success
     */
    private $interswitchSuccessCode = '00';

    /**
     * Response code for no offer
     */
    private $interswitchNoOfferCode = '3001';

    /**
     * Response code for declined offer
     */
    private $interswitchDeclinedOfferCode = '4001';

    /**
     * Response code for offer expired
     */
    private $interswitchOfferExpiredCode = '4002';

    /**
     * Response code for unknown offer
     */
    private $interswitchUnknownOfferCode = '4004';

    /**
     * Response code for system error
     */
    private $interswitchSystemErrorCode = '5000';

    /**
     * Response code for validation error
     */
    private $interswitchValidationError = '104';

    /**
     * Send success message
     */
    protected function sendInterswitchSuccessMessage($message, $status = 200, $data = null)
    {
        return $this->buildInterswitchResponse($this->interswitchSuccessCode, $message, $status, $data);
    }

    /**
     * Send custom error message
     */
    protected function sendInterswitchCustomMessage($responseCode, $message, $status, $headers = [])
    {
        return $this->buildInterswitchResponse($responseCode, $message, $status, null, $headers);
    }

    /**
     * Send no offer message
     */
    protected function sendInterswitchNoOfferMessage($message, $status = 400)
    {
        return $this->buildInterswitchResponse($this->interswitchNoOfferCode, $message, $status);
    }

    /**
     * Send declined offer message
     */
    protected function sendInterswitchDeclinedOfferMessage($message, $status = 400)
    {
        return $this->buildInterswitchResponse($this->interswitchDeclinedOfferCode, $message, $status);
    }

    /**
     * Send offer expired message
     */
    protected function sendInterswitchOfferExpiredMessage($message, $status = 400)
    {
        return $this->buildInterswitchResponse($this->interswitchOfferExpiredCode, $message, $status);
    }

    /**
     * Send unknown offer message
     */
    protected function sendInterswitchUnknownOfferMessage($message, $status = 400)
    {
        return $this->buildInterswitchResponse($this->interswitchUnknownOfferCode, $message, $status);
    }

    /**
     * Send system error message
     */
    protected function sendInterswitchSystemErrorMessage($message, $status = 500)
    {
        return $this->buildInterswitchResponse($this->interswitchSystemErrorCode, $message, $status);
    }

    /**
     * Send validation error message
     */
    protected function sendInterswitchValidationErrorMessage($message, $status = 422)
    {
        return $this->buildInterswitchResponse($this->interswitchValidationError, $message, $status);
    }

    /**
     * Send outstanding loan error message
     */
    protected function sendInterswitchOutstandingLoanErrorMessage($message, $status = 400, $code = '104')
    {
        return $this->buildInterswitchResponse($code, $message, $status);
    }

    /**
     * Send outstanding loan error message
     */
    protected function sendInterswitchUncollectedLoanErrorMessage($message, $status = 400, $code = '104')
    {
        return $this->buildInterswitchResponse($code, $message, $status);
    }

    /**
     * Send internal customer not found message
     */
    protected function sendInterswitchInternalCustomerNotFoundMessage($message, $status = 404, $code = '104')
    {
        return $this->buildInterswitchResponse($code, $message, $status);
    }

    /**
     * Send customer ineligible error message
     */
    protected function sendInterswitchCustomerIneligibleErrorMessage($message, $status = 400, $code = '104')
    {
        return $this->buildInterswitchResponse($code, $message, $status);
    }

    /**
     * Send customer blacklisted error message 
     */
    protected function sendInterswitchCustomerBlacklistedErrorMessage($message, $status = 400, $code = '104')
    {
        return $this->buildInterswitchResponse($code, $message, $status);
    }

    /**
     * Send customer account number blocked
     */
    protected function sendInterswitchCustomerAccountNumberBlocked($message, $status = 400, $code = '104')
    {
        return $this->buildInterswitchResponse($code, $message, $status);
    }

    /**
     * The XML success response
     */
    protected function sendInterswitchXmlSuccessMessage($message, $status = 200, $data = null)
    {
        return $this->buildInterswitchXmlResponse($this->interswitchSuccessCode, $message, $status, $data);
    }

    /**
     * The XML error response
     */
    protected function sendInterswitchXmlErrorMessage($code, $message, $status = 400, $headers = [])
    {
        return $this->buildInterswitchXmlResponse($code, $message, $status, null, $headers);
    }

    /**
     * Build the response
     */
    private function buildInterswitchResponse($code, $message, $status, $data = null, $headers = [])
    {
        $body = [
            'responseCode' => $code,
            'responseMessage' => $message
        ];

        if (!isset($data)) {
            return response($body, $status, $headers);
        }

        return response(array_merge($body, $data), $status, $headers);
    }

    /**
     * Build the XML response
     */
    private function buildInterswitchXmlResponse($code, $message = null, $status = 200, $data = null, $headers = [])
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Create the envelope
        $envelope = $dom->createElementNS('http://www.w3.org/2003/05/soap-envelope', 'soap:Envelope');
        $envelope->setAttribute('xmlns:isw', 'http://techquest.interswitchng.com/nameenquiry/');
        $envelope->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $envelope->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');

        // Create the element for the soap body
        $body = $dom->createElement('soap:Body');

        // Create the name enquiry response
        $nameEnquiryResponse = $dom->createElement('isw:NameEnquiryResponse');

        // Create the name enquiry response result
        $nameEnquiryResponseResult = $dom->createElement('isw:NameEnquiryResponseResult');

        // The response code 
        $responseCode = $dom->createElement('isw:ResponseCode');
        $responseCode->textContent = $code;

        $nameEnquiryResponseResult->appendChild($responseCode);

        /**
         * If data is returned
         */
        if (isset($data)) {
            /**
             * For the customer ID
             */
            $customerId = $dom->createElement('isw:CustomerID');
            $customerId->textContent = $data['customerAccountNumber'] ?? 'N/A';

            // Add the customer ID to the response result
            $nameEnquiryResponseResult->appendChild($customerId);
            
            /**
             * For the customer Name
             */
            $customerName = $dom->createElement('isw:CustomerName');

            $lastName = $dom->createElement('isw:LastName');
            $lastName->textContent = !empty($data['LastName']) ? $data['LastName'] : 'N/A';
            $customerName->appendChild($lastName);

            $otherNames = trim($data['OtherNames']);
            $otherNames = preg_replace('/\s+/', ' ', $otherNames);
            $otherNames = explode(' ', $otherNames);

            $firstName = $dom->createElement('isw:FirstName');
            $firstName->textContent = !empty($otherNames[0]) ? $otherNames[0] : 'N/A';
            $customerName->appendChild($firstName);

            $middleName = $dom->createElement('isw:OtherNames');
            $middleName->textContent = !empty($otherNames[1]) ? $otherNames[1] : '';
            $customerName->appendChild($middleName);

            // Add the customer name to the response result
            $nameEnquiryResponseResult->appendChild($customerName);

            /**
             * For the customer address
             */
            $customerAddress = $dom->createElement('isw:CustomerAddress');

            $addressLine1 = $dom->createElement('isw:AddrLine1');
            $addressLine1->textContent = !empty($data['Address']) ? $data['Address'] : 'N/A';
            $customerAddress->appendChild($addressLine1);

            $addressLine2 = $dom->createElement('isw:AddrLine2');
            $addressLine2->textContent = '';
            $customerAddress->appendChild($addressLine2);

            $city = $dom->createElement('isw:City');
            $city->textContent =  !empty($data['DistrictOfResidence']) ? $data['DistrictOfResidence'] : 'N/A';
            $customerAddress->appendChild($city);

            $stateCode = $dom->createElement('isw:StateCode');
            $stateCode->textContent = !empty($data['State']) ? $data['State'] : 'N/A';
            $customerAddress->appendChild($stateCode);

            $postalCode = $dom->createElement('isw:PostalCode');
            $postalCode->textContent = $data['PostalAddress'] ?? '';
            $customerAddress->appendChild($postalCode);

            // Add the customer ID to the response result
            $nameEnquiryResponseResult->appendChild($customerAddress);

            /**
             * For the customer phone number
             */
            $customerPhoneNumber = $dom->createElement('isw:CustomerPhoneNo');
            $customerPhoneNumber->textContent = !empty($data['PhoneNumber']) ? app()->make(NigerianPhone::class)->convert($data['PhoneNumber']) : 'N/A';

            // Add the customer phone number to the response result
            $nameEnquiryResponseResult->appendChild($customerPhoneNumber);

            /**
             * For the customer account type
             */
            $accountType = $dom->createElement('isw:AccountType');
            $accountType->textContent = '00';
            
            // Add the customer ID to the response result
            $nameEnquiryResponseResult->appendChild($accountType);

            /**
             * For the account currency
             */
            $accountCurrency = $dom->createElement('isw:AccountCurrency');
            $accountCurrency->textContent = config('services.interswitch.default_currency_code') ?? '566';
            
            // Add the account currency to the response result
            $nameEnquiryResponseResult->appendChild($accountCurrency);

            /**
             * For the country code
             */
            $countryCode = $dom->createElement('isw:CountryCode');
            $countryCode->textContent = 'NG';

            // Add the account country code to the response result
            $nameEnquiryResponseResult->appendChild($countryCode);

            /**
             * For the identification
             */
            $identification = $dom->createElement('isw:Identification');

            $idType = $dom->createElement('isw:IdType');
            $idType->textContent = 'BVN';
            $identification->appendChild($idType);

            $idNumber = $dom->createElement('isw:IdNumber');
            $idNumber->textContent = $data['BankVerificationNumber'] ?? 'N/A';
            $identification->appendChild($idNumber);

            $countryOfIssue = $dom->createElement('isw:CountryOfIssue');
            $countryOfIssue->textContent = 'Nigeria';
            $identification->appendChild($countryOfIssue);

            $expiryDate = $dom->createElement('isw:ExpiryDate');
            $expiryDate->textContent = '';
            $identification->appendChild($expiryDate);

            // Add the identification to the response result
            $nameEnquiryResponseResult->appendChild($identification);

            /**
             * For the nationality
             */
            $nationality = $dom->createElement('isw:Nationality');
            $nationality->textContent = !empty($data['Nationale']) ? $data['Nationale'] : 'N/A';
            
            // Add the nationality to the response result
            $nameEnquiryResponseResult->appendChild($nationality);

            /**
             * For the date of birth
             */
            $dob = $dom->createElement('isw:DOB');
            $dob->textContent = !empty($data['DateOfBirth']) ? Carbon::parse($data['DateOfBirth'])->format('d/m/Y') : 'N/A';

            // Add the date of birthi to the response result
            $nameEnquiryResponseResult->appendChild($dob);

            /**
             * For the country of birth
             */
            $countryOfBirth = $dom->createElement('isw:CountryOfBirth');
            $countryOfBirth->textContent = !empty($data['Nationale']) ? $data['Nationale'] : 'N/A';

            // Add the date of birthi to the response result
            $nameEnquiryResponseResult->appendChild($countryOfBirth);
        } else {
            $responseMessage = $dom->createElement('isw:ResponseMessage');
            $responseMessage->textContent = trim($message);
            
            $nameEnquiryResponseResult->appendChild($responseMessage);
        }
    
        $nameEnquiryResponse->appendChild($nameEnquiryResponseResult);
        $body->appendChild($nameEnquiryResponse);
        $envelope->appendChild($body);
        $dom->appendChild($envelope);

        return response($dom->saveXML(null, LIBXML_NOEMPTYTAG), $status, array_merge($headers, [
            'Content-Type' => 'application/xml'
        ]));
    }

}
