<?php

namespace Crm;

class CrmMockData
{
    public static string $customer = <<<JSON
        {
            "id": 1,
            "username": "casino",
            "first_name": "Brady",
            "last_name": "Wolf",
            "language": "fr",
            "email": "casino+casino@gmail.com",
            "phone_number": "123456789",
            "description": null,
            "type": "real_user",
            "created_at": "2024-01-06 01:02:03",
            "updated_at": "2024-05-14 10:18:37",
            "roles": [
                "ROLE_USER",
                "ROLE_SUBSCRIBED"
            ],
            "pictures": null,
            "logo": null,
            "domain": {
                "id": 1,
                "api_url": "https://test.com",
                "client_id": "4f6003b2ed7a836e6aad2c97615d9b9d",
                "client_secret": "09ebc3fc82011bb0e81c3874c00397f3579169f6875fabd892a83da9763298b00f1bb01b7a2db6f0e4075fa0de53b9abd5e7476f1e880a6d44a65d3190ba2009",
                "created_at": "2024-05-14 10:18:33",
                "updated_at": "2024-05-14 10:18:33",
                "status": "active",
                "maintenance_whitelist_ips": [],
                "name": "Member zone casino",
                "company_id": 1,
                "title": "Member zone casino",
                "language": "fr",
                "allowed_countries": [ "UA", "LV", "FR", "ES" ],
                "secondary_language": [ "en", "de", "en-AU" ],
                "languages": [ "fr", "en", "de", "en-AU" ],
                "currencies": [
                    {
                        "id": 1,
                        "status": "enabled",
                        "currency": "EUR",
                        "updatedAt": "2024-05-14 10:18:33"
                    }
                ],
                "logo": null,
                "slug": "memberzoneweb-casino-docker",
                "withdrawal_limits": [
                    {
                        "id": 1,
                        "currency": "EUR",
                        "period": 70,
                        "amount": 130
                    }
                ],
                "vipLevels": [
                    {
                        "id": 1,
                        "rank": 1,
                        "name": "Bronze",
                        "thumbnail": null
                    },
                    {
                        "id": 2,
                        "rank": 2,
                        "name": "Silver",
                        "thumbnail": null
                    },
                    {
                        "id": 3,
                        "rank": 3,
                        "name": "Gold",
                        "thumbnail": null
                    },
                    {
                        "id": 4,
                        "rank": 4,
                        "name": "Platinum",
                        "thumbnail": null
                    }
                ],
                "cdd_payin_limit_amount": 2000,
                "cdd_payin_limit_interval": 180,
                "platform_type": "celesta"
            },
            "last_connection_at": null,
            "affiliate_id": 3,
            "tracker": null,
            "successful_deposit_count": 173,
            "status": "active",
            "country_phone_code_prefix": "254",
            "gender": "male",
            "address": "445 Mount Eden Road, Mount Eden, Auckland",
            "zip_code": "220",
            "city": "Toronto",
            "country": "FR",
            "currency": "EUR",
            "birth_date": "2000-02-02 00:00:00",
            "marketing_allowed": false,
            "communicationChannels": {
                "smsChannel": null,
                "postChannel": null,
                "callChannel": null,
                "emailChannel": null
            },
            "deposit_allowed": true,
            "email_verification_status": true,
            "iban": "IE95IXKO45897217916065",
            "bic": "YPMUIHO5B76",
            "exclusion_start_at": null,
            "exclusion_end_at": null,
            "exclusion_end_at_delayed": null,
            "exclusion_end_at_delayed_applies_at": null,
            "registration_ip": null,
            "kyc": {
                "id": 1,
                "documents_to_validate_count": 1,
                "oldest_document_to_validate_at": "2024-05-14 10:18:57",
                "pending_withdrawal_amount": "0.00",
                "pending_withdrawal_count": 0,
                "status": "not_verified"
            },
            "customer_name": "Brady Wolf",
            "hvc_level": "0",
            "vip_level": null,
            "reason": null,
            "reality_check": null,
            "fraud_risk_score": null,
            "max_session_time": null,
            "max_session_time_delayed": null,
            "max_session_time_applies_at": null,
            "bank": {
                "accountNumber": null,
                "name": null,
                "country": null,
                "address": null,
                "bsb": null,
                "transitCode": null,
                "fin": null
            },
            "last_connection_browser": null,
            "last_connection_platform": null,
            "login_attempts_failed": 0,
            "annualGrossIncome": null,
            "monthlyDepositExpected": null,
            "employmentStatus": null,
            "rg_score": "low",
            "aml_score": "medium",
            "countryOfBirth": null,
            "nationality": null,
            "temporary_password_token": "TEST_TOKEN",
            "temporary_password_token_created_at": {
                "date": "2024-05-14 10:18:33.000000",
                "timezone_type": 3,
                "timezone": "UTC"
            },
            "otherSow": null,
            "registeredAt": "2024-01-01 01:02:03",
            "mfaEnabled": false,
            "pep": "to_screen",
            "sanctions": "to_screen",
            "watchlist": "to_screen",
            "crimelist": "to_screen"
        }
    JSON;

    public static string $vipLevels = <<<JSON
        [
            {
                "id": 1,
                "rank": 1,
                "name": "Bronze"
            },
            {
                "id": 2,
                "rank": 2,
                "name": "Silver"
            },
            {
                "id": 3,
                "rank": 3,
                "name": "Gold"
            }
        ]
    JSON;

    public static string $segment = <<<JSON
        {
            "id": 1,
            "domainId": 1,
            "name": "Segment 1",
            "assignedPlayersCount": 0,
            "createdAt": "2022-01-22 00:00:00",
            "updatedAt": "2022-01-22 00:00:00",
            "status": "enabled",
            "currency": "EUR",
            "segmentCriterias": [
                {
                    "id": 1,
                    "segment_id": 1,
                    "name": "deposit_count",
                    "type": "numerical",
                    "operator": "equal",
                    "value": [ 0 ]
                }
            ]
        }
    JSON;
}
