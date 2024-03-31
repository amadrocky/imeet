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
}