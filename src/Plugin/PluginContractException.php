<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

use RuntimeException;

/** Raised when a plugin asks for a contract its manifest does not declare. */
final class PluginContractException extends RuntimeException
{
}
