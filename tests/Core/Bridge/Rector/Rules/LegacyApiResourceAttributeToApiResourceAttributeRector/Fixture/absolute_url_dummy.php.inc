<?php

namespace ApiPlatform\Tests\Bridge\Rector\Rules\LegacyApiResourceAttributeToApiResourceAttributeRector\Fixture;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Api\UrlGeneratorInterface;

#[ApiResource(urlGenerationStrategy: UrlGeneratorInterface::ABS_URL)]
class AbsoluteUrlDummy
{
}

?>
-----
<?php

namespace ApiPlatform\Tests\Bridge\Rector\Rules\LegacyApiResourceAttributeToApiResourceAttributeRector\Fixture;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Core\Api\UrlGeneratorInterface;

#[ApiResource(urlGenerationStrategy: UrlGeneratorInterface::ABS_URL)]
#[Get]
#[Put]
#[Patch]
#[Delete]
#[GetCollection]
#[Post]
class AbsoluteUrlDummy
{
}

?>
