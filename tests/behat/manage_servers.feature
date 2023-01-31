@block @block_oppia_mobile_export
Feature: Adding a new server to the block configuration

Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |  idnumber  |
      | user1    | user      |     1    | user1@example.com  |     u1     |
    And the following "courses" exist:
      | fullname | shortname  | format |
      | Course 1 |   course_1 | topics |
    And the following "course enrolments" exist:
      |  user   |   course    |       role      |
      |  user1  |  course_1   |  editingteacher |

  @javascript
  Scenario: Go to the page to manage servers
    When I log in as "user1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Oppia Mobile Export" block
    And I follow "Add/delete server connection"

    Then I should see "OppiaMobile Servers"


  @javascript
  Scenario: Add a new server
    When I log in as "user1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Oppia Mobile Export" block
    And I follow "Add/delete server connection"
    Then I should see "OppiaMobile Servers"
    And I set the field "server_ref" to "New server"
    And I set the field "server_url" to "http://newserver.com"
    And I click on "Save changes" "button"
    
    Then I should see "OppiaMobile Servers"
    And I should see "New server"


  @javascript
  Scenario: Try to add server with empty URL
    When I log in as "user1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Oppia Mobile Export" block
    And I follow "Add/delete server connection"
    Then I should see "OppiaMobile Servers"
    And I set the field "server_ref" to "New server"
    And I set the field "server_url" to ""
    And I click on "Save changes" "button"
    
    Then I should see "OppiaMobile Servers"
    Then I should see "Please enter the url to the server."
    