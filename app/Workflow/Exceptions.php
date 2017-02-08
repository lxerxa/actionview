<?php

namespace App\Workflow;

class StateNotActivatedException extends \UnexpectedValueException {}
class StepNotFoundException extends \UnexpectedValueException {}
class CurrentStepNotFoundException extends \UnexpectedValueException {}
class ActionNotFoundException extends \UnexpectedValueException {}
class ActionNotAvailableException extends \UnexpectedValueException {}
class ResultNotFoundException extends \UnexpectedValueException {}
class ResultNotAvailableException extends \UnexpectedValueException {}
class FunctionNotFoundException extends \BadMethodCallException {}
class EntryNotFoundException extends \UnexpectedValueException {}
class ConfigNotFoundException extends \UnexpectedValueException {}
class SplitNotFoundException extends \UnexpectedValueException {}
class JoinNotFoundException extends \UnexpectedValueException {}
