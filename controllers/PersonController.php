<?php

namespace andahrm\report\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use andahrm\person\models\Person;
use andahrm\person\models\Detail as PersonDetail;
use andahrm\person\models\Religion;
use andahrm\person\models\Education;
use andahrm\person\models\EducationLevel;
use andahrm\report\models\PersonSearch;
use andahrm\positionSalary\models\PersonPosition;
use andahrm\structure\models\FiscalYear;
use andahrm\structure\models\Position;
use andahrm\structure\models\Section;
use andahrm\structure\models\PositionType;
//use andahrm\positionSalary\models\PersonPositionSalary;
use andahrm\report\models\PersonPositionSalary;
use andahrm\report\models\PersonType;
use andahrm\report\models\PersonLeave;
use andahrm\report\models\YearSearch;
use andahrm\report\models\Contract;
use yii\helpers\ArrayHelper;

/**
 * Default controller for the `report` module
 */
class PersonController extends Controller {

    /**
     * Renders the index view for the module
     * @return string
     */
    public function actions() {
        $this->layout = 'person-menu-left';
    }

    public function actionIndex() {
        $searchModel = new PersonSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->pagination = false;
        $header = [];
        if ($get = Yii::$app->request->get('PersonSearch')) {
            if (isset($get['person_type_id'])) {
                $personType = PersonType::findOne($get['person_type_id']);
                if ($get['person_type_id'] != PersonSearch::NO_SELECT_POSITION) {
                    $header[] = 'แบ่งตามประเภทบุคคล '
                            . $personType->title;
                }
            }
            if (isset($get['year'])) {
                $header[] = 'ประจำปี '
                        . ($get['year'] + 543);
            }
        }

        //$dataProvider->pagination->pageSize = Yii::$app->params['app-settings']['reading']['pagesize'];

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
                    'header' => $header,
        ]);
    }

    public function actionPosition() {
        $model = PersonPosition::find()->all();
        return $this->render('position', ['model' => $model]);
    }

    public function actionType() {

        $models['year-search'] = new YearSearch();
        $models['year-search']->load(Yii::$app->request->get());


        $modelPerson = Person::find()
                //->select('count(distinct(person.user_id))')
                //->distinct('person.user_id')
                ->joinWith('positionSalary.position');
        //->where('position.person_type_id = person_type.id');
        if ($models['year-search']->year !== null && !empty($models['year-search']->year)) {
            $y = intval($models['year-search']->year);
            $dateBetween = FiscalYear::getDateBetween($y);

            $modelPerson->andWhere(['>=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_start])
                    ->andWhere(['<=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_end]);
        }

        $modelPerson = $modelPerson
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->orderBy(['position.person_type_id' => SORT_ASC])
                ->all();




        $modelPersonType = PersonType::find();
        //$query->select(['person_type.*','count'=>$person]);
        //$query->leftJoin('position', 'position.person_type_id = person_type.id')
        //      ->leftJoin('person_position_salary', 'person_position_salary.position_id = position.id');

        $modelPersonType->andWhere(['!=', 'parent_id', '0']);

        //$query->groupBy(['position.person_type_id']);
        $modelPersonType = $modelPersonType
                ->orderBy(['id' => SORT_ASC])
                ->all();


        $newModel = new PersonType();
        $newModel->id = PersonSearch::NO_SELECT_POSITION;
        $newModel->title = 'อื่นๆ - ไม่ได้ระบุตำแหน่ง';

        $modelPersonType[] = $newModel;

        $newModelPersonType = [];
        foreach ($modelPersonType as $personType) {
            $personType->count_person = 0;
            $newModelPersonType[$personType->id] = $personType;
        }
        $modelPersonType = $newModelPersonType;

        // print_r($modelPersonType);
        // exit();

        $keys = array_keys($modelPersonType);
//echo $array[$keys[0]];


        $oldPersonTypeId = $modelPersonType[$keys[0]]->id;
        $newCount = 0;
        foreach ($modelPerson as $person) {
            if (isset($person->position->person_type_id) && $oldPersonTypeId != $person->position->person_type_id) {
                $oldPersonTypeId = $person->position->person_type_id;
                $newCount = 0;
            }
            // echo $oldPositionTypeId;
            // exit();
            if (isset($person->position->person_type_id) && $oldPersonTypeId == $person->position->person_type_id) {
                $newCount++;
                $modelPersonType[$oldPersonTypeId]->count_person = $newCount;
            } elseif (empty($person->position)) {
                //echo $oldPositionTypeId;
                $modelPersonType[PersonSearch::NO_SELECT_POSITION]->count_person = ++$modelPersonType[PersonSearch::NO_SELECT_POSITION]->count_person;
            }
        }

        $dataProvider = new ArrayDataProvider([
            'allModels' => $modelPersonType,
            'pagination' => false,
            'sort' => [
                'defaultOrder' => [
                //'parent_id' => SORT_ASC,
                //'sort'=>SORT_ASC,
                ]
            ],
        ]);


        return $this->render('type', [
                    'model' => $modelPersonType,
                    'dataProvider' => $dataProvider,
                    'models' => $models
        ]);
    }

    public function actionGender() {
        //$models['person-type'] = PersonType::find()->all();
        $models['year-search'] = new YearSearch();
        if (!$models['year-search']->load(Yii::$app->request->get())) {
            //$models['year-search']->year = date('Y');
        }

        $genderMaleCount = Person::find()
                ->select('count(distinct(person.user_id))')
                ->joinWith('positionSalary.position')
                ->where('position.person_type_id = person_type.id')
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->andWhere(['gender' => 'm']);

        $genderFemaleCount = Person::find()
                ->select('count(distinct(person.user_id))')
                ->joinWith('positionSalary.position')
                ->where('position.person_type_id = person_type.id')
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->andWhere(['gender' => 'f']);

        $noGenderCount = Person::find()
                ->select('count(distinct(person.user_id))')
                ->joinWith('positionSalary.position')
                ->where('position.person_type_id = person_type.id')
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->andWhere('gender IS NULL');

        if ($models['year-search']->year !== null && !empty($models['year-search']->year)) {
            $y = intval($models['year-search']->year);
            $dateBetween = FiscalYear::getDateBetween($y);

            $genderMaleCount->andWhere(['>=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_start])
                    ->andWhere(['<=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_end]);

            $genderFemaleCount->andWhere(['>=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_start])
                    ->andWhere(['<=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_end]);
        }


        $query = PersonType::find();
        $query->select([
            'person_type.*',
            'genderMaleCount' => $genderMaleCount,
            'genderFemaleCount' => $genderFemaleCount,
            'noGenderCount' => $noGenderCount
        ]);
        $query->andWhere(['!=', 'parent_id', '0']);
        $modelPersonType = $query->all();

        ######################################################

        $modelPersonType = PersonType::find()
                ->andWhere(['!=', 'parent_id', '0']);
        $modelPersonType = $modelPersonType
                ->orderBy(['id' => SORT_ASC])
                ->all();


        $newModel = new PersonType();
        $newModel->id = PersonSearch::NO_SELECT_POSITION;
        $newModel->title = 'อื่นๆ - ไม่ได้ระบุตำแหน่ง';
        $modelPersonType[] = $newModel;

        $modelPersonType = ArrayHelper::index($modelPersonType, 'id');

        // $newModelPersonType = [];
        // foreach($modelPersonType as $personType){
        //     $personType->count_person = 0;
        //     $newModelPersonType[$personType->id] = $personType;
        // }
        // $modelPersonType=$newModelPersonType;


        $modelGender = Person::find()->groupBy('gender')
                ->select(['gender'])
                ->orderBy(['gender' => SORT_DESC])
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->asArray()
                ->all();
        $modelGender[PersonSearch::NO_GENDER]['gender'] = PersonSearch::NO_GENDER;
        $modelGender = ArrayHelper::index($modelGender, 'gender');
        $modelGender[PersonSearch::NO_GENDER]['gender'] = 'ไม่ได้ระบุเพศ';

        // print_r($modelGender);
        // exit();
        // $newModelGender = [];
        // foreach($modelGender as $gender){
        //     $personType->count_person = 0;
        //     $newModelGender[$personType->id] = $personType;
        // }
        // $modelGender=$newModelGender;



        $modelPerson = Person::find()
                ->select(['position.person_type_id', 'person.gender', 'person.user_id', 'citizen_id'])
                ->joinWith('positionSalary.position');
        if ($models['year-search']->year !== null && !empty($models['year-search']->year)) {
            $y = intval($models['year-search']->year);
            $dateBetween = FiscalYear::getDateBetween($y);

            $modelPerson->andWhere(['>=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_start])
                    ->andWhere(['<=', 'DATE(person_position_salary.adjust_date)', $dateBetween->date_end]);
        }

        $modelPerson = $modelPerson
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->orderBy(['position.person_type_id' => SORT_ASC, 'gender' => SORT_DESC])
                ->asArray()
                ->all();
        $modelPerson = ArrayHelper::index($modelPerson, null, [function ($element) {
                        return $element['person_type_id'];
                    }, 'gender']);
        // echo "<pre>";
        // print_r($modelPerson);
        // exit();

        $keys = array_keys($modelGender);
        $oldGender = $keys[0];

        $keys = array_keys($modelPersonType);
        $oldPersonTypeId = $modelPersonType[$keys[0]]->id;
        $newCount = 0;
        $mCount = 0;
        $fCount = 0;
        foreach ($modelPerson as $key => $personType) {

            // echo "<pre>";
            // print_r(array_keys($personType));
            // print_r($personType);
            // exit();

            if ($key) {
                $modelPersonType[$key]->genderMaleCount = isset($personType['m']) ? count($personType['m']) : 0;
                $modelPersonType[$key]->genderFemaleCount = isset($personType['f']) ? count($personType['f']) : 0;
                $modelPersonType[$key]->noGenderCount = isset($personType['']) ? count($personType['']) : 0;
            } elseif ($key == 0) {
                $modelPersonType[PersonSearch::NO_SELECT_POSITION]->genderMaleCount = isset($personType['m']) ? count($personType['m']) : 0;
                $modelPersonType[PersonSearch::NO_SELECT_POSITION]->genderFemaleCount = isset($personType['f']) ? count($personType['f']) : 0;

                $modelPersonType[PersonSearch::NO_SELECT_POSITION]->noGenderCount = isset($personType['']) ? count($personType['']) : 0;
            }


            // }elseif($key == 0){
            //         $modelPersonType[PersonSearch::NO_SELECT_POSITION]->genderMaleCount  = count($person['m']) ;
            //             $modelPersonType[PersonSearch::NO_SELECT_POSITION]->genderFemaleCount =  count($person['f']) ;
            //         $modelPersonType[PersonSearch::NO_SELECT_POSITION]->noGenderCount += 1;
            // }
        }






        $dataProvider = new ArrayDataProvider([
            'allModels' => $modelPersonType,
            'pagination' => false,
            'sort' => [
                'defaultOrder' => [
                //'parent_id' => SORT_ASC,
                //'sort'=>SORT_ASC,
                ]
            ],
        ]);

        return $this->render('gender', ['model' => $modelPersonType, 'models' => $models, 'dataProvider' => $dataProvider,]);
    }

    public function actionLevel() {
        $models['person-level'] = \andahrm\structure\models\PositionLevel::find()->all();
        foreach ($models['person-level'] as $key => $level) {
            $query = PersonPositionSalary::find()
                    ->select(['levelPersonCount' => 'COUNT(*)'])
                    ->joinWith('position')
                    ->joinWith('user')
                    ->where(['position.position_level_id' => $level->id]);

            // $models['person-position-salary'][$level->id] = $query->asArray()
            // ->one();
            $models['person-position-salary'][$level->id] = $query->one();
        }

        // return $this->renderContent('ssss');
        // echo '<pre>';
        // echo $models['person-position-salary'][2]->PersonCount;
        // print_r($models['person-position-salary']);
        // exit();
        //Yii::$app->end();

        return $this->render('level', ['models' => $models]);
    }

    public function actionLeave() {
        $models['person'] = PersonPositionSalary::find()->all();
        $models['leaveType'] = \andahrm\leave\models\LeaveType::find()->all();

        $newSelect = [];

        $newSelect = ArrayHelper::getColumn($models['leaveType'], function ($element) {
                    return "SUM(CASE WHEN leave_type_id = {$element->id} THEN 1 ELSE 0 END) as type" . $element->id;
                });


        // print_r($newSelect);
        // exit();

        $query = PersonLeave::find()
                ->joinWith('leaveType')
                ->select(ArrayHelper::merge(['leave.created_by'], $newSelect))
                ->groupBy(['created_by']);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
                // 'sort' => [
                //     'defaultOrder' => [
                //         'created_at' => SORT_DESC,
                //         'title' => SORT_ASC, 
                //     ]
                // ],
        ]);

        $models['provider'] = $provider;



        return $this->render('leave'
                        , ['models' => $models]
        );
    }

    public function actionReligion() {

        $modelPerson = Person::find()->joinWith('detail')
                        ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                        ->orderBy(['person_detail.religion_id' => SORT_ASC])->all();
        $modelPerson = ArrayHelper::index($modelPerson, null, 'detail.religion_id');
        // echo "<pre>";
        // print_r($modelPerson);
        // exit();


        $modelReligion = Religion::find()->orderBy(['id' => SORT_ASC])->all();
        $newReligion = new Religion();
        $newReligion->id = PersonSearch::NO_RELIGION;
        $newReligion->title = 'ไม่ได้เลือกศาสนา';
        $newReligion->count_person = 0;
        $modelReligion[] = $newReligion;

        // echo "<pre>";
        // print_r($modelReligion);
        // exit();

        $old_relagion_id = $modelReligion[0]->id;
        foreach ($modelReligion as $religion) {
            if ($old_relagion_id != $religion->id) {
                $old_relagion_id = $religion->id;
            }


            if ($old_relagion_id == $religion->id && isset($modelPerson[$old_relagion_id])) {
                $religion->count_person = count($modelPerson[$old_relagion_id]);
            } elseif ($religion->id == PersonSearch::NO_RELIGION && $modelPerson['']) {
                $religion->count_person = count($modelPerson['']);
            }
        }



        $dataProvider = new ArrayDataProvider([
            'allModels' => $modelReligion
        ]);

        return $this->render('religion', [
                    'modelReligion' => $modelReligion,
                    'dataProvider' => $dataProvider
        ]);
    }

    public function actionDegree() {

        $modelUser = Person::find()
                ->joinWith('detail.education')
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->orderBy(['person_education.level_id' => SORT_ASC])
                ->all();
        $modelUser = ArrayHelper::index($modelUser, null, 'education.level_id');

        $modelDegree = EducationLevel::find()->all();

        $newDegree = new EducationLevel();
        $newDegree->id = PersonSearch::NO_DEGREE;
        $newDegree->title = "ไม่ได้เลือกการศึกษา";
        $newDegree->count_person = 0;
        $modelDegree[] = $newDegree;

        // print_r($modelDegree);
        // exit();

        $modelDegrees = [];
        $f_degree = $modelDegree[0]->id;
        $count = 0;
        foreach ($modelDegree as $degree) {

            if ($f_degree != $degree->id) {
                $f_degree = $degree->id;
                $count = 0;
            }

            if ($f_degree == $degree->id && isset($modelUser[$f_degree])) {
                $count = count($modelUser[$f_degree]);
            } elseif ($f_degree == PersonSearch::NO_DEGREE && $modelUser['']) {
                $count = count($modelUser['']);
            }

            //if($count){
            $degree->count_person = $count ? $count : 0;
            $modelDegrees[$f_degree] = $degree;
            //}
        }
        $dataProvider = new ArrayDataProvider([
            'allModels' => $modelDegrees,
            'pagination' => false,
        ]);

        return $this->render('degree', [
                    'modelDegree' => $modelDegrees,
                    'dataProvider' => $dataProvider
        ]);
    }

    public function actionPositionType() {



        $modelPositionType = PositionType::find()
                //->from('position_type as ssss')
                //->where(['!=', "title",'อัตราเงินเดือน' ])
                //->select(['*','count_person'=>$modelPerson])
                ->orderBy(['person_type_id' => SORT_ASC, 'id' => SORT_ASC])
                ->all();

        $modelPerson = Person::find()
                ->joinWith('position')
                //->joinWith('position',true,"INNER JOIN")
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->orderBy(['position.position_type_id' => SORT_ASC])
                ->all();
        // echo "<pre>";
        // print_r($modelPerson);
        // exit();

        $newModel = new PositionType();
        $newModel->id = PersonSearch::NO_SELECT_POSITION_TYPE;
        $newModel->title = "ไม่ได้เลือกประเภทตำแหน่ง";
        $newModel->count_person = 0;
        $newModel->person_type_id = 0;
        $modelPositionType[] = $newModel;

        $newModel = new PositionType();
        $newModel->id = PersonSearch::NO_SELECT_POSITION;
        $newModel->title = "ไม่ได้เลือกตำแหน่ง";
        $newModel->count_person = 0;
        $newModel->person_type_id = 0;
        $modelPositionType[] = $newModel;

        $newModelPositionType = [];
        foreach ($modelPositionType as $positionType) {
            $positionType->count_person = 0;
            $newModelPositionType[$positionType->id] = $positionType;
        }
        $modelPositionType = $newModelPositionType;
        // echo "<pre>";
        // print_r($modelPositionType);
        // exit();

        $oldPositionTypeId = $modelPositionType[1]->id;
        // echo $modelPerson[0]['position']['position_type_id'];
        // exit();
        $newModelPositionType = [];
        $newCount = 0;
        foreach ($modelPerson as $person) {
            if (isset($person->position->position_type_id) && $oldPositionTypeId != $person->position->position_type_id) {
                $oldPositionTypeId = $person->position->position_type_id;
                $newCount = 0;
            }
            // echo $oldPositionTypeId;
            // exit();
            if (isset($person->position->position_type_id) && $oldPositionTypeId == $person->position->position_type_id) {
                $newCount++;
                $modelPositionType[$oldPositionTypeId]->count_person = $newCount;
            } elseif (!empty($person->position) && empty($person->position->position_type_id)) {
                //echo $oldPositionTypeId;
                $modelPositionType[PersonSearch::NO_SELECT_POSITION_TYPE]->count_person = ++$modelPositionType[PersonSearch::NO_SELECT_POSITION_TYPE]->count_person;
            } elseif (empty($person->position)) {
                //echo $oldPositionTypeId;
                $modelPositionType[PersonSearch::NO_SELECT_POSITION]->count_person = ++$modelPositionType[PersonSearch::NO_SELECT_POSITION]->count_person;
            }
        }
        // echo "<pre>";
        // print_r($modelDegrees);
        // exit();

        $dataProvider = new ArrayDataProvider([
            'allModels' => $modelPositionType,
            'pagination' => false,
        ]);

        return $this->render('position-type', [
                    'models' => $modelPositionType,
                    'dataProvider' => $dataProvider
        ]);
    }

    public function actionSection() {

        $modelPerson = PersonPositionSalary::find()
                ->select('count(distinct(user_id))')
                ->joinWith('position')
                ->where('position.section_id = ssss.id');
        //->groupBy('position.section_id');

        $modelPerson = Person::find()
                ->joinWith('position')
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->orderBy(['position.section_id' => SORT_ASC])
                ->all();


        $modelSection = Section::find()
                ->orderBy(['id' => SORT_ASC]);
        $modelSection = $modelSection->all();

        $newModel = new Section();
        $newModel->id = PersonSearch::NO_SELECT_POSITION;
        $newModel->title = 'อื่นๆ - ไม่ได้ระบุตำแหน่ง';

        $modelSection[] = $newModel;

        $newModelSection = [];
        foreach ($modelSection as $section) {
            $section->count_person = 0;
            $newModelSection[$section->id] = $section;
        }
        $modelSection = $newModelSection;

        // print_r($modelSection);
        // exit();


        $oldSectionId = $modelSection[1]->id;
        $newCount = 0;
        foreach ($modelPerson as $person) {
            if (isset($person->position->section_id) && $oldSectionId != $person->position->section_id) {
                $oldSectionId = $person->position->section_id;
                $newCount = 0;
            }
            // echo $oldPositionTypeId;
            // exit();
            if (isset($person->position->section_id) && $oldSectionId == $person->position->section_id) {
                $newCount++;
                $modelSection[$oldSectionId]->count_person = $newCount;
            } elseif (empty($person->position)) {
                //echo $oldPositionTypeId;
                $modelSection[PersonSearch::NO_SELECT_POSITION]->count_person = ++$modelSection[PersonSearch::NO_SELECT_POSITION]->count_person;
            }
        }


        $dataProvider = new ArrayDataProvider([
            'allModels' => $modelSection,
            'pagination' => false,
        ]);

        return $this->render('section', [
                    'models' => $modelSection,
                    'dataProvider' => $dataProvider
        ]);
    }

    public function actionRangeAge() {

        $range_age = [];

        $range_age[] = [
            'title' => 'ต่ำกว่า 20',
            'start' => 0,
            'end' => 20,
            'count_person' => 0,
            'data' => []
        ];
        $range_age[] = [
            'title' => '21 - 30',
            'start' => 21,
            'end' => 30,
            'count_person' => 0,
            'data' => []
        ];
        $range_age[] = [
            'title' => '31 - 40',
            'start' => 31,
            'end' => 40,
            'count_person' => 0,
            'data' => []
        ];
        $range_age[] = [
            'title' => '41 - 50',
            'start' => 41,
            'end' => 50,
            'count_person' => 0,
            'data' => []
        ];
        $range_age[] = [
            'title' => '51 - 60',
            'start' => 51,
            'end' => 60,
            'count_person' => 0,
            'data' => []
        ];
        $range_age[] = [
            'title' => 'มากกว่า 60',
            'start' => 61,
            'end' => 100,
            'count_person' => 0,
            'data' => []
        ];

        $range_age[PersonSearch::NO_BIRTHDAY] = [
            'title' => 'ไม่ได้ระบุอายุ',
            'start' => 0,
            'end' => 0,
            'count_person' => 0,
            'data' => []
        ];

        $modelPerson = Person::find()
                ->select(['user_id', 'timestampdiff(YEAR,birthday,NOW()) as age', 'birthday'])
                ->andWhere(['!=', 'person.status', Person::STATUS_RETIRED])
                ->orderBy(['birthday' => SORT_DESC])
                //->asArray()
                ->all();

        // echo "<pre>";
        // print_r($modelPerson);
        // exit();

        $index = 0;
        $count_person = 0;
        $rangeOld = $range_age[$index];
        $data = [];
        foreach ($modelPerson as $model) {


            if ($model->birthday !== null && $model->age > $range_age[$index]['end']) {
                ++$index;
                $count_person = 0;
                $data = [];
            }

            if (isset($range_age[$index]) && $model->age >= $range_age[$index]['start'] && $model->age <= $range_age[$index]['end']) {
                $count_person++;
                $range_age[$index]['count_person'] = $count_person;
                $range_age[$index]['data'][] = ['id' => $model->user_id, 'age' => $model->age];
            } elseif ($model->birthday === null) {

                $range_age[PersonSearch::NO_BIRTHDAY]['count_person'] += 1;
                $range_age[PersonSearch::NO_BIRTHDAY]['data'][] = ['id' => $model->user_id, 'age' => $model->age];
            }
        }

//        echo "<pre>";
//        print_r($range_age);
//        exit();
        $dataProvider = new ArrayDataProvider([
            'allModels' => $range_age,
            'pagination' => false,
        ]);


        return $this->render('range-age', [
                    'dataProvider' => $dataProvider,
                    'models' => $range_age,
        ]);
    }

}
