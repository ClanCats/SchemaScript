<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Exception\EvaluatorException;

trait EvaluatorErrorTrait
{
    /**
     * @return never
     */
    private function throwEvaluatorError(string $message, ?BaseNode $node, EvaluationContext $context): never
    {
        $e = new EvaluatorException($message);
        if ($node !== null && $node->getSourceLine() !== null) {
            $e->setSourceContext(
                $node->getSourceLine(),
                $node->getSourceColumn() ?? 0,
                $node->getSourceFile(),
                $context->getSourceCode($node->getSourceFile())
            );
        }
        throw $e;
    }
}
