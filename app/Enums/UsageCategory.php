<?php

namespace App\Enums;

/** Un usage déclaré, jamais une autorisation. */
enum UsageCategory: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Professional = 'professional';
    case Individual = 'individual';
}
