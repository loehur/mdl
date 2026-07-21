<?php

class Training extends Controller
{
    public function __construct()
    {
        $this->session_cek();
    }

    /**
     * Switch mode: Live | Training
     * POST mode = live|training
     */
    public function switchMode()
    {
        header('Content-Type: application/json; charset=utf-8');

        $mode = strtolower(trim((string) ($_POST['mode'] ?? '')));
        if (!in_array($mode, ['live', 'training'], true)) {
            echo json_encode(['code' => 0, 'msg' => 'Mode tidak valid']);
            return;
        }

        $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
        if ($idUser <= 0) {
            echo json_encode(['code' => 0, 'msg' => 'Session user tidak valid']);
            return;
        }

        if (!isset($_SESSION[URL::SESSID]['training']) || !is_array($_SESSION[URL::SESSID]['training'])) {
            $_SESSION[URL::SESSID]['training'] = ['active' => false, 'id_cabang_origin' => 0];
        }

        if ($mode === 'training') {
            $trainId = $this->getTrainingCabangId();
            if ($trainId <= 0) {
                echo json_encode([
                    'code' => 0,
                    'msg' => 'Cabang training belum siap. Jalankan SQL laundry/tools/mode_training.sql di VPS.',
                ]);
                return;
            }

            // Origin = cabang real dari DB (bukan override session)
            $dbUser = $this->db(0)->get_where_row('user', 'id_user = ' . $idUser);
            $origin = (int) ($dbUser['id_cabang'] ?? 0);
            if ($origin === $trainId) {
                $origin = (int) ($_SESSION[URL::SESSID]['training']['id_cabang_origin'] ?? 0);
            }
            if ($origin <= 0 || $origin === $trainId) {
                $ops = $this->getCabangOperasional();
                $origin = !empty($ops[0]['id_cabang']) ? (int) $ops[0]['id_cabang'] : 0;
            }

            $_SESSION[URL::SESSID]['training']['active'] = true;
            $_SESSION[URL::SESSID]['training']['id_cabang_origin'] = $origin;
        } else {
            $_SESSION[URL::SESSID]['training']['active'] = false;
        }

        $this->dataSynchrone($idUser);

        echo json_encode([
            'code' => 1,
            'msg' => $mode === 'training' ? 'Mode Training aktif' : 'Mode Live aktif',
            'mode' => $mode,
        ]);
    }
}
