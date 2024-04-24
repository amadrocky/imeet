<?php

namespace App\Helpers;

class Constants
{
    public const PDF_FOLDER_PATH = '/var/PDF/';
    
    public const QRCODES_FOLDER_PATH = '/public/qrCodes/';

    public const ORDER_STATE_PAID = 'paid';

    public const TICKET_STATE_ACTIVE = 'active';
    public const TICKET_STATE_SCANNED = 'scanned';

    public const WELCOME_EMAIL_TEMPLATE = 15;
    public const UPGRADE_PASSWORD_EMAIL_TEMPLATE = 16;
    public const ORDER_CONFIRMATION_EMAIL_TEMPLATE = 17;

    public const CHART_RED_COLOR = 'rgba(220, 53, 69, .7)';
    public const CHART_BLUE_COLOR = 'rgba(10, 28, 60, .9)';

    public const E_TICKETS_PRODUCT_NUMBER = 50;
    public const REPORTS_PRODUCT_NUMBER = 100;

    public const STRIPE_PAYMENT_STATUS_PAID = "paid";
}