# TruthShield ECPay Donation Setup

## Local Defaults

The local Docker stack uses ECPay staging values:

- `ECPAY_CHECKOUT_URL=https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5`
- `ECPAY_MERCHANT_ID=<ECPAY_STAGING_MERCHANT_ID>`
- `ECPAY_HASH_KEY=<ECPAY_STAGING_HASH_KEY>`
- `ECPAY_HASH_IV=<ECPAY_STAGING_HASH_IV>`

## Production Checklist

1. Set production `ECPAY_MERCHANT_ID`, `ECPAY_HASH_KEY`, and `ECPAY_HASH_IV` only on the backend server.
2. Set `ECPAY_CHECKOUT_URL=https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5`.
3. Set `ECPAY_API_BASE_URL` to the public API origin so ECPay can reach `/api/donations/ecpay/notify`.
4. Set `ECPAY_WEB_BASE_URL` to the public website origin for the return page.
5. Run `php artisan truthshield:check-production-env`.
6. Submit a small live transaction and confirm the donation appears as `paid` in the admin panel.

## Data Handling

TruthShield stores donation order metadata and ECPay callback payloads. Credit card numbers are handled by ECPay and are not stored by TruthShield.
