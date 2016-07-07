@order_association @cli
Feature: Generate order associations feature
  In order to associate products by orders
  As a Developer
  I want to run a command that associates products by orders

  Background:
    Given there are following orders:
      | number    | customer                      | state   |
      | 000000001 | alysson.aufderhar@example.com | pending |
      | 000000002 | imelda87@example.com          | cart    |
      | 000000003 | yousef@example.com            | pending |
      | 000000004 | gmurray@example.com           | cart    |
      | 000000005 | meta.hegmann@example.com      | cart    |
      | 000000006 | alysson.aufderhar@example.com | cart    |
      | 000000007 | jwaelchi@example.com          | pending |
      | 000000008 | imelda87@example.com          | pending |
      | 000000009 | imelda87@example.com          | cart    |
      | 000000010 | yousef@example.com            | cart    |
    And there are following products:
      | name                              |
      | Sticker 'perferendis'             |
      | T-Shirt 'provident'               |
      | Mug 'est'                         |
      | Mug 'ullam'                       |
      | T-Shirt 'quia'                    |
      | T-Shirt 'molestiae'               |
      | Mug 'non'                         |
      | Sticker 'nihil'                   |
      | T-Shirt 'et'                      |
      | Mug 'molestias'                   |
      | Book 'Ut' by 'Cecile McCullough'  |
      | Book 'Sapiente' by 'Elroy Hickle' |
      | Book 'Aut' by 'Herta Cronin'      |
      | Sticker 'non'                     |
      | Mug 'debitis'                     |
      | Sticker 'doloremque'              |
      | Sticker 'sit'                     |
      | Mug 'dignissimos'                 |
    And order "000000001" has the following products:
      | name                              |
      | Sticker 'non'                     |
    And order "000000002" has the following products:
      | name                              |
      | T-Shirt 'et'                      |
      | Mug 'ullam'                       |
      | Book 'Aut' by 'Herta Cronin'      |
    And order "000000003" has the following products:
      | name                              |
      | Book 'Ut' by 'Cecile McCullough'  |
    And order "000000004" has the following products:
      | name                              |
      | Book 'Sapiente' by 'Elroy Hickle' |
      | Sticker 'nihil'                   |
    And order "000000006" has the following products:
      | name                              |
      | Sticker 'perferendis'             |
      | Sticker 'nihil'                   |
      | Mug 'ullam'                       |
      | T-Shirt 'quia'                    |
    And order "000000007" has the following products:
      | name                              |
      | Sticker 'nihil'                   |
      | Sticker 'doloremque'              |
      | Mug 'dignissimos'                 |
    And order "000000008" has the following products:
      | name                              |
      | Sticker 'nihil'                   |
      | Sticker 'perferendis'             |
      | Mug 'ullam'                       |
    And order "000000009" has the following products:
      | name                              |
      | T-Shirt 'provident'               |
    And order "000000010" has the following products:
      | name                              |
      | Sticker 'perferendis'             |

  Scenario: Trying to run command without existing association type
    Given there is no "webburza_order_association_bundle" association type named "Customers also ordered"
    And I run Webburza Sylius Order Association Generate command
    Then I should see output "Creating association type..."
    And there should be "webburza_order_association_bundle" association type named "Customers also ordered"
    And the command should finish successfully

  Scenario: Trying to run command with existing association type
    Given there is a "webburza_order_association_bundle" association type named "Customers also ordered"
    And I run Webburza Sylius Order Association Generate command
    Then I should not see output "Creating association type..."
    And the command should finish successfully

  Scenario: Trying to generate associations for products
    Given I run Webburza Sylius Order Association Generate command
    Then I should see output "Updating product associations with new data..."
    And the command should finish successfully
    And product "Sticker 'perferendis'" should only be associated with:
      | name                  |
      | Mug 'ullam'           |
      | Sticker 'nihil'       |
    And product "Mug 'ullam'" should only be associated with:
      | name                  |
      | Sticker 'perferendis' |
      | Sticker 'nihil'       |
    And product "Sticker 'nihil'" should only be associated with:
      | name                  |
      | Sticker 'perferendis' |
      | Mug 'ullam'           |
      | Sticker 'doloremque'  |
      | Mug 'dignissimos'     |
    And product "Sticker 'doloremque'" should only be associated with:
      | name                  |
      | Sticker 'nihil'       |
      | Mug 'dignissimos'     |
    And product "Mug 'dignissimos'" should only be associated with:
      | name                  |
      | Sticker 'nihil'       |
      | Sticker 'doloremque'  |
    And the following products should have no associations:
      | name                              |
      | T-Shirt 'provident'               |
      | Mug 'est'                         |
      | T-Shirt 'quia'                    |
      | T-Shirt 'molestiae'               |
      | Mug 'non'                         |
      | T-Shirt 'et'                      |
      | Mug 'molestias'                   |
      | Book 'Ut' by 'Cecile McCullough'  |
      | Book 'Sapiente' by 'Elroy Hickle' |
      | Book 'Aut' by 'Herta Cronin'      |
      | Sticker 'non'                     |
      | Mug 'debitis'                     |
      | Sticker 'sit'                     |

  Scenario: Trying to generate a limited number of associations per product
    Given association limit per product is 2
    And I run Webburza Sylius Order Association Generate command
    Then I should see output "Updating product associations with new data..."
    And the command should finish successfully
    And product "Sticker 'nihil'" should only be associated with:
      | name                  |
      | Sticker 'perferendis' |
      | Mug 'ullam'           |
