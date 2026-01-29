<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Command\AddInvoiceItemCommand;
use App\Application\Command\CreateInvoiceCommand;
use App\Application\Command\IssueInvoiceCommand;
use App\Application\Command\RemoveInvoiceItemCommand;
use App\Application\Command\UpdateInvoiceItemQuantityCommand;
use App\Application\Query\GetInvoiceListQuery;
use App\Application\Query\GetInvoiceQuery;
use App\Application\Query\Handler\GetInvoiceListQueryHandler;
use App\Application\Query\Handler\GetInvoiceQueryHandler;
use App\Domain\Shared\ValueObject\Uuid;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/invoices', name: 'api_invoices_')]
#[OA\Tag(name: 'Invoices')]
final class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly GetInvoiceQueryHandler $getInvoiceQueryHandler,
        private readonly GetInvoiceListQueryHandler $getInvoiceListQueryHandler,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new invoice',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'invoiceNumber', type: 'string', example: 'INV-2025-001'),
                    new OA\Property(property: 'customerId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                    new OA\Property(property: 'issueDate', type: 'string', format: 'date', example: '2025-01-25'),
                    new OA\Property(property: 'dueDate', type: 'string', format: 'date', example: '2025-02-25'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Invoice created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Bad request'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $invoiceId = Uuid::random()->value();
        $command = new CreateInvoiceCommand(
            $invoiceId,
            $data['invoiceNumber'] ?? '',
            $data['customerId'] ?? '',
            new \DateTimeImmutable($data['issueDate'] ?? 'now'),
            new \DateTimeImmutable($data['dueDate'] ?? '+30 days')
        );

        $errors = $this->validator->validate($command);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch($command);

            return $this->json([
                'id' => $invoiceId,
                'message' => 'Invoice created successfully',
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get invoice by ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invoice found',
                content: new OA\JsonContent(ref: new Model(type: \App\Application\Query\ViewModel\InvoiceViewModel::class))
            ),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function get(string $id): JsonResponse
    {
        $query = new GetInvoiceQuery($id);
        $invoice = $this->getInvoiceQueryHandler->handle($query);

        if ($invoice === null) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($invoice->toArray());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get invoice list',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invoice list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'invoices',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: \App\Application\Query\ViewModel\InvoiceViewModel::class))
                        ),
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'limit', type: 'integer'),
                        new OA\Property(property: 'totalPages', type: 'integer'),
                    ]
                )
            ),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page') ?? 1);
        $limit = (int) ($request->query->get('limit') ?? 10);

        $query = new GetInvoiceListQuery($page, $limit);
        $result = $this->getInvoiceListQueryHandler->handle($query);

        return $this->json([
            'invoices' => array_map(fn($invoice) => $invoice->toArray(), $result->invoices),
            'total' => $result->total,
            'page' => $result->page,
            'limit' => $result->limit,
            'totalPages' => $result->totalPages(),
        ]);
    }

    #[Route('/{id}/items', name: 'add_item', methods: ['POST'])]
    #[OA\Post(
        summary: 'Add item to invoice',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'productId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                    new OA\Property(property: 'description', type: 'string', example: 'Product description'),
                    new OA\Property(property: 'quantity', type: 'integer', example: 2),
                    new OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 99.99),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Item added successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function addItem(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new AddInvoiceItemCommand(
            $id,
            $data['productId'] ?? '',
            $data['description'] ?? '',
            (int) ($data['quantity'] ?? 0),
            (float) ($data['unitPrice'] ?? 0)
        );

        $errors = $this->validator->validate($command);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Item added successfully']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/items/{itemIndex}', name: 'remove_item', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Remove item from invoice',
        description: 'Removes an item from a draft invoice by index',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemIndex', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item removed successfully'),
            new OA\Response(response: 400, description: 'Bad request - invoice must be in draft or invalid index'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function removeItem(string $id, int $itemIndex): JsonResponse
    {
        $command = new RemoveInvoiceItemCommand($id, (int) $itemIndex);

        $errors = $this->validator->validate($command);
        if (\count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Item removed successfully']);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'not found') ? Response::HTTP_NOT_FOUND : Response::HTTP_BAD_REQUEST;

            return $this->json(['error' => $e->getMessage()], $status);
        }
    }

    #[Route('/{id}/items/{itemIndex}', name: 'update_item_quantity', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Update item quantity',
        description: 'Updates the quantity of an item in a draft invoice',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemIndex', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 0)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Item quantity updated successfully'),
            new OA\Response(response: 400, description: 'Bad request - draft only, invalid quantity or index'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function updateItemQuantity(string $id, int $itemIndex, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 0;

        $command = new UpdateInvoiceItemQuantityCommand($id, (int) $itemIndex, $quantity);

        $errors = $this->validator->validate($command);
        if (\count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Item quantity updated successfully']);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'not found') ? Response::HTTP_NOT_FOUND : Response::HTTP_BAD_REQUEST;

            return $this->json(['error' => $e->getMessage()], $status);
        }
    }

    #[Route('/{id}/issue', name: 'issue', methods: ['POST'])]
    #[OA\Post(
        summary: 'Issue invoice',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice issued successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function issue(string $id): JsonResponse
    {
        $command = new IssueInvoiceCommand($id);

        try {
            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Invoice issued successfully']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
