<?php

namespace App\Enums;

abstract class SoftStatus extends BasicEnum
{
    const Pending = 0;
    const Active = 1;
    const Archived = 2;
    const Deleted = 3;
}
