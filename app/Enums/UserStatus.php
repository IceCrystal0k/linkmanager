<?php
namespace App\Enums;

abstract class UserStatus extends BasicEnum
{
    const Pending = 0;
    const Active = 1;
    const Deleted = 2;
}