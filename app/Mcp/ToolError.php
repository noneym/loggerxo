<?php

namespace App\Mcp;

use RuntimeException;

/**
 * A problem the calling model can act on (bad argument, unknown id). Its message is returned as the tool result.
 */
class ToolError extends RuntimeException {}
