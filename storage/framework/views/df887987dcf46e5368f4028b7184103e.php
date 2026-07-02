<?php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
?>
# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use ___SINGLE_BACKTICK___<?php echo e($assist->artisanCommand('make:test --phpunit {name}')); ?>___SINGLE_BACKTICK___ to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: ___SINGLE_BACKTICK___<?php echo e($assist->artisanCommand('test --compact')); ?>___SINGLE_BACKTICK___.
- To run all tests in a file: ___SINGLE_BACKTICK___<?php echo e($assist->artisanCommand('test --compact tests/Feature/ExampleTest.php')); ?>___SINGLE_BACKTICK___.
- To filter on a particular test name: ___SINGLE_BACKTICK___<?php echo e($assist->artisanCommand('test --compact --filter=testName')); ?>___SINGLE_BACKTICK___ (recommended after making a change to a related file).
<?php /**PATH /Users/sakshamjamwal/Desktop/work/chatApp-Backend/storage/framework/views/ad027bc1f4f77db585097342990de57b.blade.php ENDPATH**/ ?>