<?php
namespace Database;

class EDatabaseException extends \Exception {};
class ECouldNotConnectToServer extends EDatabaseException {};
class EDatabaseMissing extends EDatabaseException {};
class EDuplicateKey extends EDatabaseException {};

class MySQLiCouldNotConnectToServer extends ECouldNotConnectToServer {};
