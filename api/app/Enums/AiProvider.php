<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case OpenAiCompatible = 'openai_compatible';
}
