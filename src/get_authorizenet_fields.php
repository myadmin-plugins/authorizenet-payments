<?php

/**
 * Returns a list of all the fields in an authorize.net csv response
 * with name and description and the idx of each element matches
 * @return array an array of  idx => name/description fields for authorize.net
 */
function get_authorizenet_fields()
{
	$fields = [
		[
			'name' => 'Response Code',
			'description' => ': The overall status of the transaction Format:
								1 = Approved
								2 = Declined
								3 = Error
								4 = Held for review'
		],
		[
			'name' => 'Response Subcode',
			'description' => ': A code used by the payment gateway for internal transaction tracking'
		],
		[
			'name' => 'Response Reason Code',
			'description' => ': A code that represents more details about the result of the transaction
								: Numeric
								, for a listing of response reason codes.'
		],
		[
			'name' => 'Response Reason Text',
			'description' => ': A brief description of the result that corresponds with the response reason code
								: Text
								, to identify any specific texts you do not want to pass to the customer.'
		],
		[
			'name' => 'Authorization Code',
			'description' => ': The authorization or approval code
								: 6 characters'
		],
		[
			'name' => 'AVS Response',
			'description' => ': The Address Verification Service (AVS) response code Format:
								A = Address (Street) matches, ZIP does not
								B = Address information not provided for AVS check
								E = AVS error
								G = Non-U.S. Card Issuing Bank
								N = No Match on Address (Street) or ZIP
								P = AVS not applicable for this transaction
								R = Retry = System unavailable or timed out
								S = Service not supported by issuer
								U = Address information is unavailable
								W = Nine digit ZIP matches, Address (Street) does not
								X = Address (Street) and nine digit ZIP match
								Y = Address (Street) and five digit ZIP match
								Z = Five digit ZIP matches, Address (Street) does not
								: Indicates the result of the AVS filter.
								.'
		],
		[
			'name' => 'Transaction ID',
			'description' => ': The payment gateway-assigned identification number for the transaction
								: When x_test_request is set to a positive response, or when Test Mode is enabled on the payment gateway, this value is 0.
								: This value must be used for any follow-on transactions such as a CREDIT, PRIOR_AUTH_CAPTURE, or VOID.'
		],
		[
			'name' => 'Invoice Number',
			'description' => ': The merchant-assigned invoice number for the transaction
								: 20-character maximum (no symbols)'
		],
		[
			'name' => 'Description',
			'description' => ': The transaction description
								: 255-character maximum (no symbols)'
		],
		[
			'name' => 'Amount',
			'description' => ': The amount of the transaction
								: 15-digit maximum'
		],
		[
			'name' => 'Method',
			'description' => ': The payment method
								CC or ECHECK'
		],
		[
			'name' => 'Transaction Type',
			'description' => ': The type of credit card transaction
								: AUTH_CAPTURE, AUTH_ONLY, CAPTURE_ONLY, CREDIT, PRIOR_AUTH_CAPTUREVOID'
		],
		[
			'name' => 'Customer ID',
			'description' => ': The merchant-assigned customer ID
								: 20-character maximum (no symbols)'
		],
		[
			'name' => 'First Name',
			'description' => ': The first name associated with the customer\'s billing							address
								: 50-character maximum (no symbols)'
		],
		[
			'name' => 'Last Name',
			'description' => ': The last name associated with the customer\'s billing address
								: 50-character maximum (no symbols)'
		],
		[
			'name' => 'Company',
			'description' => ': The company associated with the customer\'s billing							address
								: 50-character maximum (no symbols)'
		],
		[
			'name' => 'Address',
			'description' => ': The customer\'s billing address
								: 60-character maximum (no symbols)'
		],
		[
			'name' => 'City',
			'description' => ': The city of the customer\'s billing address
								: 40-character maximum (no symbols)'
		],
		[
			'name' => 'State',
			'description' => ': The state of the customer\'s billing address
								: 40-character maximum (no symbols) or a valid
								2-character state code'
		],
		[
			'name' => 'ZIP Code',
			'description' => ': The ZIP code of the customer\'s billing address
								: 20-character maximum (no symbols)'
		],
		[
			'name' => 'Country',
			'description' => ': The country of the customer\'s billing address
								: 60-character maximum (no symbols)'
		],
		[
			'name' => 'Phone',
			'description' => ': The phone number associated with the customer\'s billing							address
								: 25-character maximum (no letters). For example, (123)123-1234'
		],
		[
			'name' => 'Fax',
			'description' => ': The fax number associated with the customer\'s billing							address
								: 25-digit maximum (no letters). For example, (123)123-1234'
		],
		[
			'name' => 'Email Address',
			'description' => ': The customer\'s valid email address
								: 255-character maximum'
		],
		[
			'name' => 'Ship To First Name',
			'description' => ': The first name associated with the customer\'s shipping address
								: 50-character maximum (no symbols)'
		],
		[
			'name' => 'Ship To Last Name',
			'description' => ': The last name associated with the customer\'s shipping address
								: 50-character maximum (no symbols)'
		],
		[
			'name' => 'Ship To Company',
			'description' => ': The company associated with the customer\'s shipping address
								: 50-character maximum (no symbols)'
		],
		[
			'name' => 'Ship To Address',
			'description' => ': The customer\'s shipping address
								: 60-character maximum (no symbols)'
		],
		[
			'name' => 'Ship To City',
			'description' => ': The city of the customer\'s shipping address
								: 40-character maximum (no symbols)'
		],
		[
			'name' => 'Ship To State',
			'description' => ': The state of the customer\'s shipping address
								: 40-character maximum (no symbols) or a valid 2character state code'
		],
		[
			'name' => 'Ship To ZIP Code',
			'description' => ': The ZIP code of the customer\'s shipping address
								: 20-character maximum (no symbols)'
		],
		[
			'name' => 'Ship To Country',
			'description' => ': The country of the customer\'s shipping address
								: 60-character maximum (no symbols)'
		],
		[
			'name' => 'Tax',
			'description' => ': The tax amount charged
								: Numeric
								: Delimited tax information is not included in the transaction response.'
		],
		[
			'name' => 'Duty',
			'description' => ': The duty amount charged
								: Numeric
								: Delimited duty information is not included in the transaction response.'
		],
		[
			'name' => 'Freight',
			'description' => ': The freight amount charged
								: Numeric
								: Delimited freight information is not included in the transaction response.'
		],
		[
			'name' => 'Tax Exempt',
			'description' => ': The tax exempt status
								: TRUE, FALSE, T, F, YES, NO, Y, N, 1, 0'
		],
		[
			'name' => 'Purchase Order Number',
			'description' => ': The merchant-assigned purchase order number
								: 25-character maximum (no symbols)'
		],
		[
			'name' => 'MD5 Hash',
			'description' => ': The payment gateway-generated MD5 hash value that can be used to authenticate the transaction response.
								: Optional. Transaction responses are returned using SSL/ TLS, so this field is useful mainly as a redundant security check.'
		],
		[
			'name' => 'Card Code Response',
			'description' => ': The card code verification (CCV) response code Format:
								M = Match
								N = No Match
								P = Not Processed
								S = Should have been present
								U = Issuer unable to process request
								: Indicates the result of the CCV filter.
								.'
		],
		[
			'name' => 'Cardholder Authentication Verification Response',
			'description' => ': The cardholder authentication verification response code
								: Blank or not present = CAVV not validated
								0 = CAVV not validated because erroneous data was submitted
								1 = CAVV failed validation
								2 = CAVV passed validation
								3 = CAVV validation could not be performed; issuer attempt incomplete
								4 = CAVV validation could not be performed; issuer system error
								5 = Reserved for future use
								6 = Reserved for future use
								7 = CAVV attempt = failed validation = issuer available (U.S.issued card/non-U.S acquirer)
								8 = CAVV attempt = passed validation = issuer available (U.S.issued card/non-U.S. acquirer)
								9 = CAVV attempt = failed validation = issuer unavailable S.-issued card/non-U.S. acquirer)
								A = CAVV attempt = passed validation = issuer unavailable S.-issued card/non-U.S. acquirer)
								B = CAVV passed validation, information only, no liability shift'
		],
		[
			'name' => 'Account Number',
			'description' => ': Last 4 digits of the card provided
								: Alphanumeric (XXXX6835)
								: This field is returned with all transactions.'
		],
		[
			'name' => 'Card Type',
			'description' => ': Visa, MasterCard, American Express, Discover, Diners Club, JCB
								: Text'
		]
	];
	return $fields;
}
