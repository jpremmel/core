<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\EventListener;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\OperationDataProviderTrait;
use ApiPlatform\Core\DataProvider\SubresourceDataProviderInterface;
use ApiPlatform\Core\Identifier\IdentifierConverterInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ToggleableOperationAttributeTrait;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Core\Util\CloneTrait;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use ApiPlatform\Core\Util\RequestParser;
use ApiPlatform\Exception\InvalidIdentifierException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Symfony\EventListener\ReadListener as SymfonyReadListener;
use ApiPlatform\Util\OperationRequestInitiatorTrait;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Retrieves data from the applicable data provider and sets it as a request parameter called data.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @deprecated
 */
final class ReadListener
{
    use CloneTrait;
    use OperationDataProviderTrait;
    use OperationRequestInitiatorTrait;
    use ToggleableOperationAttributeTrait;

    public const OPERATION_ATTRIBUTE_KEY = 'read';

    private $serializerContextBuilder;

    public function __construct(CollectionDataProviderInterface $collectionDataProvider, ItemDataProviderInterface $itemDataProvider, SubresourceDataProviderInterface $subresourceDataProvider = null, SerializerContextBuilderInterface $serializerContextBuilder = null, IdentifierConverterInterface $identifierConverter = null, ResourceMetadataFactoryInterface $resourceMetadataFactory = null, ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null)
    {
        $this->collectionDataProvider = $collectionDataProvider;
        $this->itemDataProvider = $itemDataProvider;
        $this->subresourceDataProvider = $subresourceDataProvider;
        $this->serializerContextBuilder = $serializerContextBuilder;
        $this->identifierConverter = $identifierConverter;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
        trigger_deprecation('api-platform/core', '2.7', sprintf('The listener "%s" is deprecated and will be replaced by "%s" in 3.0.', __CLASS__, SymfonyReadListener::class));
    }

    /**
     * Calls the data provider and sets the data attribute.
     *
     * @throws NotFoundHttpException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $operation = $this->initializeOperation($request);

        if (
            !($attributes = RequestAttributesExtractor::extractAttributes($request))
            || !$attributes['receive']
            || $request->isMethod('POST') && isset($attributes['collection_operation_name'])
            || ($operation && !($operation->getExtraProperties()['is_legacy_resource_metadata'] ?? false) && !($operation->getExtraProperties()['is_legacy_subresource'] ?? false))
            || $this->isOperationAttributeDisabled($attributes, self::OPERATION_ATTRIBUTE_KEY)
        ) {
            return;
        }

        if (null === $filters = $request->attributes->get('_api_filters')) {
            $queryString = RequestParser::getQueryString($request);
            $filters = $queryString ? RequestParser::parseRequestParams($queryString) : null;
        }

        $context = null === $filters ? [] : ['filters' => $filters];
        if ($this->serializerContextBuilder) {
            // Builtin data providers are able to use the serialization context to automatically add join clauses
            $context += $normalizationContext = $this->serializerContextBuilder->createFromRequest($request, true, $attributes);
            $request->attributes->set('_api_normalization_context', $normalizationContext);
        }

        if (isset($attributes['collection_operation_name'])) {
            $request->attributes->set('data', $this->getCollectionData($attributes, $context));

            return;
        }

        $data = [];

        if ($this->identifierConverter) {
            $context[IdentifierConverterInterface::HAS_IDENTIFIER_CONVERTER] = true;
        }

        try {
            $identifiers = $this->extractIdentifiers($request->attributes->all(), $attributes);

            if (isset($attributes['item_operation_name'])) {
                $data = $this->getItemData($identifiers, $attributes, $context);
            } elseif (isset($attributes['subresource_operation_name'])) {
                // Legacy
                if (null === $this->subresourceDataProvider) {
                    throw new RuntimeException('No subresource data provider.');
                }

                $data = $this->getSubresourceData($identifiers, $attributes, $context);
            }
        } catch (InvalidIdentifierException $e) {
            throw new NotFoundHttpException('Invalid identifier value or configuration.', $e);
        }

        if (null === $data) {
            throw new NotFoundHttpException('Not Found');
        }

        $request->attributes->set('data', $data);
        $request->attributes->set('previous_data', $this->clone($data));
    }
}
