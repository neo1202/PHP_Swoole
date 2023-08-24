<?php

namespace MyApp\ServerController;

// 用來import進去
class ServerController
{
    public function addOne($number)
    {
        return $number+1;
    }
    public function getAvailableRoom($rooms)
    {
        foreach ($rooms as $oneRoom) {
            if (!$oneRoom->isFull()) {
                return $oneRoom->roomId;
            }
        }
        return null;
    }
    public function getRoomId($fd, $rooms)
    {
        $findRoomId = null;
        foreach ($rooms as $oneRoom) {
            // 檢查玩家ID是否存在於該房間的玩家ID列表中
            if (in_array($fd, array_column($oneRoom->playerIds, 0))) {
                $findRoomId = $oneRoom->roomId; // 如果存在，將房間ID設置為該房間的ID
                break; // 找到後結束迴圈
            }
        }
        return $findRoomId;
    }
}
