<?php

namespace App\Enums;

enum SettlementMode: string
{
    case JointClearinghouse = 'joint_clearinghouse';
    case DirectP2p = 'direct_p2p';
}
