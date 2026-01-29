Feature: Invoice Management
  As a user
  I want to manage invoices
  So that I can track billing

  Scenario: Create a new invoice
    Given I send a POST request to "/api/invoices" with body:
      """
      {
        "invoiceNumber": "INV-TEST-001",
        "customerId": "550e8400-e29b-41d4-a716-446655440000",
        "issueDate": "2025-01-25",
        "dueDate": "2025-02-25"
      }
      """
    Then the response status code should be 201
    And the response should contain "id"
    And the response should contain "message"

  Scenario: Get invoice by ID
    Given I have created an invoice with number "INV-2025-002"
    When I send a GET request to "/api/invoices/:invoiceId"
    Then the response status code should be 200
    And the response should contain "number"
    And the response should contain "id"

  Scenario: Add item to invoice
    Given I have created an invoice with number "INV-2025-003"
    Given I send a POST request to "/api/invoices/:invoiceId/items" with body:
      """
      {
        "productId": "550e8400-e29b-41d4-a716-446655440001",
        "description": "Test Product",
        "quantity": 2,
        "unitPrice": 99.99
      }
      """
    Then the response status code should be 200
    And the response should contain "message"

  Scenario: Issue invoice
    Given I have created an invoice with number "INV-2025-004"
    And I have added an item to the invoice
    Given I send a POST request to "/api/invoices/:invoiceId/issue"
    Then the response status code should be 200
    And the response should contain "message"

  Scenario: Remove item from draft invoice
    Given I have created an invoice with number "INV-2025-005"
    And I have added an item to the invoice
    And I send a POST request to "/api/invoices/:invoiceId/items" with body:
      """
      {
        "productId": "550e8400-e29b-41d4-a716-446655440002",
        "description": "Test Product 2",
        "quantity": 1,
        "unitPrice": 50.0
      }
      """
    When I send a DELETE request to "/api/invoices/:invoiceId/items/0"
    Then the response status code should be 200
    And the response should contain "message"

  Scenario: Cannot remove item from issued invoice
    Given I have created an invoice with number "INV-2025-006"
    And I have added an item to the invoice
    And I send a POST request to "/api/invoices/:invoiceId/issue"
    When I send a DELETE request to "/api/invoices/:invoiceId/items/0"
    Then the response status code should be 400
    And the response should contain "Can only remove items from draft invoices"

  Scenario: Update item quantity in draft invoice
    Given I have created an invoice with number "INV-2025-007"
    And I have added an item to the invoice
    When I send a PATCH request to "/api/invoices/:invoiceId/items/0" with body:
      """
      {"quantity": 5}
      """
    Then the response status code should be 200
    And the response should contain "message"

  Scenario: Cannot update item quantity in issued invoice
    Given I have created an invoice with number "INV-2025-008"
    And I have added an item to the invoice
    And I send a POST request to "/api/invoices/:invoiceId/issue"
    When I send a PATCH request to "/api/invoices/:invoiceId/items/0" with body:
      """
      {"quantity": 3}
      """
    Then the response status code should be 400
    And the response should contain "Can only update item quantity in draft invoices"
