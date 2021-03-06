<?php

class pocketlistsItemCreateAction extends waViewAction
{
    public function execute()
    {
        $data = waRequest::post('data', false, waRequest::TYPE_ARRAY);
        $filter = waRequest::post('filter', false);
        $list_id = waRequest::post('list_id', false, waRequest::TYPE_INT);
        $assigned_contact_id = waRequest::post('assigned_contact_id', false, waRequest::TYPE_INT);

        $im = new pocketlistsItemModel();
        $inserted = $inserted_items = $items = array();
        $assign_contact = null;
        $user_id = wa()->getUser()->getId();
        $canAssign = $assigned_contact_id && pocketlistsRBAC::canAssign();
        if ($canAssign) {
            $assign_contact = new waContact($assigned_contact_id);
        }

        // if no list id passed - get default list from settings
        if (!$list_id) {
            $us = new pocketlistsUserSettings($assign_contact ? $assign_contact->getId() : $user_id);
            $list_id = $us->getStreamInboxList();
        }
        if ($data) {
            $paste = false;
            if (!is_array($data)) {
                $data = array($data);
            } else {
                $paste = true;
            }
            $lm = new pocketlistsListModel();
            $list = $lm->getById($list_id);
            foreach ($data as $i => $d) {
                $data[$i]['create_datetime'] = date("Y-m-d H:i:s");
                $data[$i]['list_id'] = $list ? $list['id'] : null;
                $data[$i]['contact_id'] = $user_id;

                if ($canAssign) {
                    $data[$i]['assigned_contact_id'] = $assign_contact->getId();
                }

                if (!empty($data[$i]['due_date'])) {
                    $data[$i]['due_date'] = waDateTime::date('Y-m-d', strtotime($data[$i]['due_date']));
                }

                $data[$i]['name'] = trim($data[$i]['name']);

                // if add throught paste - cut some letters
                if ($paste) {
                    $data[$i]['name'] = preg_replace('/^(([-|—|–]*)(\s*))(.*)$/u', "$4", $data[$i]['name']);
                }

                // natural input parse
                $ni = pocketlistsNaturalInput::matchPriority($data[$i]['name']);
                if ($ni) {
                    $data[$i]['name'] = $ni['name'];
                    $data[$i]['priority'] = $ni['priority'];
                }
                $ni = pocketlistsNaturalInput::matchNote($data[$i]['name']);
                if ($ni) {
                    $data[$i]['name'] = $ni['name'];
                    $data[$i]['note'] = $ni['note'];
                }

                $us = new pocketlistsUserSettings($user_id);
                if ($us->getNaturalInput()) {
                    $ni = pocketlistsNaturalInput::matchDueDate($data[$i]['name']);
                    if ($ni) {
                        $data[$i]['due_date'] = $ni['due_date'];
                        if ($ni['due_datetime']) {
                            $tm = pocketlistsHelper::convertToServerTime(strtotime($ni['due_datetime']));
                            $data[$i]['due_datetime'] = waDateTime::date("Y-m-d H:i:s", $tm);
                        }
                    }
                }

                $im->addPriorityData($data[$i]);

                $last_id = $im->insert($data[$i], 1);

                $links = pocketlistsNaturalInput::getLinks($data[$i]['name']);

                if ($links) {
                    $linkDeterminer = new pocketlistsLinkDeterminer();
                    foreach ($links as $link) {
                        $linkAppTypeId = $linkDeterminer->getAppTypeId($link);
                        if ($link === false) {
                            continue;
                        }

                        $itemLink = new pocketlistsItemLinkModel($linkAppTypeId);
                        $itemLink->item_id = $last_id;
                        try {
                            $itemLink->save();
                        } catch (waException $ex) {
                            // silence
                        }
                    }
                }

                if (!empty($data[$i]['links'])) {
                    foreach ($data[$i]['links'] as $link) {
                        foreach ($link['model'] as $key => $value) {
                            if ($value === '') {
                                $link['model'][$key] = null;
                            }
                        }

                        $itemLink = new pocketlistsItemLinkModel($link['model']);
                        $itemLink->item_id = $last_id;
                        try {
                            $itemLink->save();
                        } catch (waException $ex) {
                            // silence
                        }
                    }
                }

                $inserted[] = $last_id;
                $inserted_items[] = $data[$i] + array('id' => $last_id);
            }

            if ($inserted) {
                switch ($filter) {
                    case 'favorites':
                        $ufm = new pocketlistsUserFavoritesModel();
                        foreach ($inserted_items as $item) {
                            $ufm->insert(array(
                                'item_id' => $item['id'],
                                'contact_id' => $user_id
                            ));
                        }
                        break;
                }

                $items = $im->getById($inserted);
                $items = $im->extendItemData($items);
                if (isset($items['id'])) {
                    $items = array($items);
                }

                if ($list) {
                    $list['name'] = pocketlistsNaturalInput::matchLinks($list['name']);
                }

                pocketlistsNotifications::notifyAboutNewItems($items, $list);

                // log this action
                foreach ($items as $item) {
                    if (!$list && !$assign_contact) {
                        $this->logAction(pocketlistsLogAction::NEW_SELF_ITEM, array(
                            'item_id' => $item['id']
                        ));
                    }

                    if ($assign_contact) {
                        pocketlistsNotifications::notifyAboutNewAssign($item);

                        $this->logAction(pocketlistsLogAction::ITEM_ASSIGN_TEAM, array(
                            'list_id' => $item['list_id'],
                            'item_id' => $item['id'],
                            'assigned_to' => $item['assigned_contact_id']
                        ));
                    }
                }

                if ($list) {
                    $this->logAction(pocketlistsLogAction::NEW_ITEMS, array('list_id' => $list['id']));
                }
            }
        }

        $this->view->assign('items', $items);

        $this->setTemplate('templates/actions/item/Item.html');
    }
}
