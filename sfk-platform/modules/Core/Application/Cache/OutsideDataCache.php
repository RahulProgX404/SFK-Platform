<?php

declare(strict_types=1);

namespace Modules\Core\Application\Cache;

/**
 * A model whose saves and deletes change none of the figures DataCache keeps (e.g. 社内メッセージ): its writes leave the
 * dashboard's cached figures alone — a busy chat would otherwise make every dashboard add them up afresh.
 */
interface OutsideDataCache {}
