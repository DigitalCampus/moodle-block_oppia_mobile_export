@block @block_oppia_mobile_export
Feature: Adding oppia_mobile_export_block to course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |  idnumber  |
      | user1    | user      |     1    | user1@example.com  |     u1     |
    And the following "courses" exist:
      | fullname | shortname  | format |
      | Course 1 |   course_1 | topics |
    And the following "activities" exist:
      | activity |   name   |        intro        |  course  | idnumber | section |
      |   page   |  Page 1  |  Page 1 description | course_1 |   page1  |    1    |
    And the following "course enrolments" exist:
      |  user   |   course    |       role      |
      |  user1  |  course_1   |  editingteacher |

  @javascript
  Scenario: Adding block oppia_mobile_export to course
    When I log in as "user1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Oppia Mobile Export" block
    And I set the following fields to these values:
      | course_status  |  Live |
    And I click on "Export to Oppia Package" "button"
    Then I should see "Export - step 1:"
    And I click on "Continue" "button"
    Then I should see "Export - step 2:"
    And I click on "Continue" "button"
    Then I should see "Export - step 3:"
    And I click on "Continue" "button"
    Then I should see "Export - step 4:"
    And I wait to be redirected
    Then I should see "Export - step 5:"
