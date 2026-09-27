<?php
declare(strict_types=1);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $page=max(1,(int)($_GET['page']??1));$perPage=min(50,max(1,(int)($_GET['per_page']??12)));$offset=($page-1)*$perPage;
 $search=query_string('search');$location=query_string('location');$category=query_string('category');$ageMin=query_string('age_min');$ageMax=query_string('age_max');$priceMax=query_string('price_max');$day=query_string('day');
 $where=['a.status=:status'];$params=['status'=>'published'];
 if($search!==''){$where[]='(a.name LIKE :search OR a.description LIKE :search OR a.town LIKE :search)';$params['search']='%'.$search.'%';}
 if($location!==''){$where[]='(a.town LIKE :location OR a.region LIKE :location OR a.address LIKE :location)';$params['location']='%'.$location.'%';}
 if($category!==''){$where[]='a.category=:category';$params['category']=$category;}
 if($ageMin!==''&&is_numeric($ageMin)){$where[]='a.age_max>=:age_min';$params['age_min']=(float)$ageMin;}
 if($ageMax!==''&&is_numeric($ageMax)){$where[]='a.age_min<=:age_max';$params['age_max']=(float)$ageMax;}
 if($priceMax!==''&&is_numeric($priceMax)){$where[]='(a.price IS NULL OR a.price<=:price_max)';$params['price_max']=(float)$priceMax;}
 if($day!==''){$where[]='EXISTS(SELECT 1 FROM activity_days ad WHERE ad.activity_id=a.id AND ad.day_name=:day)';$params['day']=$day;}
 $whereSql=implode(' AND ',$where);
 $count=$pdo->prepare("SELECT COUNT(*) FROM activities a WHERE $whereSql");$count->execute($params);$total=(int)$count->fetchColumn();
 $sql="SELECT a.id,a.name,a.slug,a.description,a.image_url,a.address,a.town,a.region,a.postcode,a.latitude,a.longitude,a.category,a.age_min,a.age_max,a.price,a.price_label,a.session_length,a.website_url,a.booking_url,a.featured FROM activities a WHERE $whereSql ORDER BY a.featured DESC,a.name ASC LIMIT :limit OFFSET :offset";
 $stmt=$pdo->prepare($sql);foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v);$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();$items=$stmt->fetchAll();
 foreach($items as &$item){$item['id']=(int)$item['id'];$item['age_min']=$item['age_min']!==null?(float)$item['age_min']:null;$item['age_max']=$item['age_max']!==null?(float)$item['age_max']:null;$item['price']=$item['price']!==null?(float)$item['price']:null;$item['featured']=(bool)$item['featured'];$s=$pdo->prepare('SELECT day_name FROM activity_days WHERE activity_id=:id ORDER BY day_order');$s->execute(['id'=>$item['id']]);$item['days']=array_column($s->fetchAll(),'day_name');}unset($item);
 json_response(['success'=>true,'data'=>$items,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$total?(int)ceil($total/$perPage):0]]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $input=json_input();if(trim((string)($input['name']??''))==='')json_response(['success'=>false,'error'=>'Missing required field: name'],422);
 $slug=strtolower(trim((string)($input['slug']??'')));if($slug===''){$slug=preg_replace('/[^a-z0-9]+/i','-',strtolower((string)$input['name']));$slug=trim($slug,'-');}
 $stmt=$pdo->prepare('INSERT INTO activities (name,slug,description,image_url,address,town,region,postcode,latitude,longitude,category,age_min,age_max,price,price_label,session_length,website_url,booking_url,featured,status) VALUES (:name,:slug,:description,:image_url,:address,:town,:region,:postcode,:latitude,:longitude,:category,:age_min,:age_max,:price,:price_label,:session_length,:website_url,:booking_url,:featured,:status)');
 $stmt->execute(['name'=>trim((string)$input['name']),'slug'=>$slug,'description'=>$input['description']??null,'image_url'=>$input['image_url']??null,'address'=>$input['address']??null,'town'=>$input['town']??null,'region'=>$input['region']??null,'postcode'=>$input['postcode']??null,'latitude'=>$input['latitude']??null,'longitude'=>$input['longitude']??null,'category'=>$input['category']??null,'age_min'=>$input['age_min']??null,'age_max'=>$input['age_max']??null,'price'=>$input['price']??null,'price_label'=>$input['price_label']??null,'session_length'=>$input['session_length']??null,'website_url'=>$input['website_url']??null,'booking_url'=>$input['booking_url']??null,'featured'=>!empty($input['featured'])?1:0,'status'=>$input['status']??'draft']);
 json_response(['success'=>true,'id'=>(int)$pdo->lastInsertId()],201);
}
json_response(['success'=>false,'error'=>'Method not allowed.'],405);