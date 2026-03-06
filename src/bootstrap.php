<?php

use losthost\SimpleAI\types\ProviderRegistry;
use losthost\SimpleAI\Provider\OpenAI\OpenAIProvider;

ProviderRegistry::register('openai', fn() => new OpenAIProvider());