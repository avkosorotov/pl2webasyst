<?php

/**
 * Class pocketlistsListUpdateController
 */
class pocketlistsListUpdateController extends waJsonController
{
    /**
     * @throws waDbException
     * @throws waException
     */
    public function execute()
    {
        if (!pocketlistsRBAC::canCreateLists()) {
            throw new waException('Access denied.', 403);
        }

        $list_id = waRequest::post('id', false, waRequest::TYPE_INT);
        $data = waRequest::post('data', false, waRequest::TYPE_ARRAY);

        if (empty($data['pocket_id'])) {
            $this->setError('no pocket id');

            return;
        }

        $pocket = pocketlistsPocketModel::model()->findByPk($data['pocket_id']);
        if (!$pocket) {
            $this->setError('no pocket ');

            return;
        }

        $lm = new pocketlistsListModel();
        if ($list_id > 0) {
            $data['id'] = $list_id;
        } else {
            $data['create_datetime'] = date("Y-m-d H:i:s");
            $data['color'] = $pocket->color;
        }
        $data['contact_id'] = wa()->getUser()->getId();

        $list_icons = pocketlistsHelper::getListIcons();
        $matched_icon = pocketlistsNaturalInput::matchCategory($data['name']);
        if ($matched_icon && isset($list_icons[$matched_icon])) {
            $data['icon'] = $list_icons[$matched_icon];
        }

        $data = $lm->add($data, 1);
        if ($data) {
            $data['name'] = pocketlistsNaturalInput::matchLinks($data['name']);
            // add access for user
            if (!pocketlistsRBAC::isAdmin()) {
                $rm = new waContactRightsModel();
                $rm->save(
                    $data['contact_id'],
                    pocketlistsHelper::APP_ID,
                    'list.'.$data['id'],
                    pocketlistsRBAC::ACCESS_VALUE
                );
            }
            // log this action
            $this->logAction(
                pocketlistsLogAction::LIST_CREATED,
                [
                    'list_id' => $data['id'],
                ]
            );
            pocketlistsNotifications::notifyAboutNewList($data);
        }

        $this->response = ['id' => $data['id']];
    }
}
