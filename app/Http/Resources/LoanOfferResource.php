<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Calculation\Money as MoneyCalculator;
use App\Models\Fee;

class LoanOfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'offerId' => (string) $this->id,
            'amountOffered' => $this->amount,
            'interest' => (float) $this->interest,
            'amountPayable' => (int) app()->make(MoneyCalculator::class, [
                'value' => $this->amount,
                'fees' => $this->fees
            ])->totalPayable($this->interest)->getValue(),
            'fees' => $this->when(isset($this->fees) && !Fee::find($this->fees)->isEmpty(), Fee::select([
                'name',
                'amount'
            ])->find($this->fees)),
            'tenure' => $this->tenure,
            'terms' => 'Please read the terms and conditions at '.config('quickfund.terms_and_conditions'),
            'expiryDate' => $this->whenNotNull($this->expiry_date?->toIso8601ZuluString()),
            'currency' => $this->currency
        ];
    }
}
