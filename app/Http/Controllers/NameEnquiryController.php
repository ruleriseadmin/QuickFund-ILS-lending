<?php

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Http\Request;
use App\Exceptions\Interswitch\XmlException;
use Illuminate\Support\Facades\Http;

class NameEnquiryController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        try {
            // Get the xml content
            $data = simplexml_load_string($request->getContent());
        } catch (Throwable $e) {
            throw new XmlException('06', $e->getMessage(), 422);
        }

        if ($data === false) {
            throw new XmlException('06', 'Request is invalid XML', 422);
        }

        // Get the customer ID
        $customerIdNode = $data->children('ns4', true)?->Body
                        ?->children('ns3', true)?->NameEnquiry
                        ?->children('ns3', true)?->NameEnquiryRequest
                        ?->children('ns3', true)?->CustomerID;

        if (!isset($customerIdNode)) {
            throw new XmlException('06', 'Customer ID is required', 422);
        }

        // Get the customer ID
        $customerId = dom_import_simplexml($customerIdNode)?->textContent;

        if (empty($customerId)) {
            throw new XmlException('06', 'Customer ID is required', 422);
        }

        try {
            $response = Http::get('https://api.quickfundmfb.com/api/v1/interswitch-name-enquiry', [
                'account_number'=> $customerId
            ]);

            // Get the body of the response
            $body = $response->json();

            if ($response->failed()) {
                throw new XmlException('06', $body['Message'], $response->status());
            }

            // Add the account number to the body
            $body['customerAccountNumber'] = $customerId;

            // A valid response was gotten
            return $this->sendInterswitchXmlSuccessMessage(null, 200, $body);
        } catch (Throwable $e2) {
            throw new XmlException('06', $e2->getMessage(), isset($response) ? $response?->status() : 503);
        }
    }
}
