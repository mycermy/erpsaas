<?php

namespace Erpsaas\Core\Models;

use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use CompanyOwned;
}
