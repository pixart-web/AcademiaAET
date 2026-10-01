<?php

namespace App\Enums;

/**
 * AET-RC01 finding 1: distinguishes didactic content a professional
 * uploads and picks for an activity's instructions from a clinical
 * recording/drawing a child produced as an answer — the two are stored in
 * the same table but must never share a policy, a library listing, or an
 * editor's media picker. A null `purpose` on an existing MediaAsset means
 * it predates this distinction and its real origin couldn't be
 * determined by the backfill migration — see MediaAssetPolicy, which
 * treats that the same as ClinicalResponse with no owner: visible to an
 * admin for manual review, never through the general instructional
 * library, and never assignable to an activity step.
 */
enum MediaPurpose: string
{
    case Instructional = 'instructional';
    case ClinicalResponse = 'clinical_response';
}
