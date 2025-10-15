<?php

namespace App\Services;

/**
 * Contract for sending SMS and OTP messages.
 *
 * Implementations should return a uniform response array:
 *
 * Success:
 *  [
 *      'success'    => true,
 *      'message_id' => string|int|null,   // provider message id if available
 *      'balance'    => mixed|null,        // remaining credit/balance if provider returns it
 *  ]
 *
 * Failure:
 *  [
 *      'success' => false,
 *      'error'   => string,               // human-readable error
 *      'code'    => string|int|null,      // optional provider error code
 *  ]
 */
interface SmsServiceInterface
{
    /**
     * Send a plain SMS message to a single recipient.
     *
     * @param  string $phoneNumber  Phone number in local (e.g. 024XXXXXXX) or international (e.g. +23324XXXXXXX / 23324XXXXXXX) format
     * @param  string $message      Message body
     * @return array{success:bool, message_id?:mixed, balance?:mixed, error?:string, code?:mixed}
     */
    public function sendSms(string $phoneNumber, string $message): array;

    /**
     * Send a one-time password (OTP) to a single recipient.
     *
     * Implementations may format the OTP message internally (e.g., “Your OTP is 123456…”)
     *
     * @param  string $phoneNumber  Phone number in local or international format
     * @param  string $otpCode      The 6-digit (or provider-accepted) OTP code
     * @return array{success:bool, message_id?:mixed, balance?:mixed, error?:string, code?:mixed}
     */
    public function sendOtp(string $phoneNumber, string $otpCode): array;
}
