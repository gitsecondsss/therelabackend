import express from "express";
import crypto from "crypto";
import fetch from "node-fetch";

const app = express();
app.use(express.json());

const SECRET = process.env.RAILWAY_SECRET;
const CF_SECRET = "0x4AAAAAACEAdSoSffFlw4Y93xBl0UFbgsc";

function sign(payload){
  return crypto
    .createHmac("sha256", SECRET)
    .update(JSON.stringify(payload))
    .digest("hex");
}

app.post("/verify-human", async (req,res)=>{
  const { cf_token, metrics } = req.body;

  // Cloudflare verification
  const cf = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify",{
    method:"POST",
    headers:{ "Content-Type":"application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      secret: CF_SECRET,
      response: cf_token
    })
  }).then(r=>r.json());

  let humanScore = 0;
  if(cf.success) humanScore += 3;
  if(!metrics.webdriver) humanScore += 2;
  if(metrics.timing > 300) humanScore += 2;
  if(metrics.screen) humanScore += 1;

  // Fake vs real token
  const isHuman = humanScore >= 5;

  const tokenPayload = {
    h: isHuman ? 1 : 0,
    ts: Date.now(),
    n: crypto.randomBytes(8).toString("hex")
  };

  const trust_token = Buffer.from(JSON.stringify({
    ...tokenPayload,
    sig: sign(tokenPayload)
  })).toString("base64");

  res.json({
    ok: isHuman,
    trust_token
  });
});

app.listen(process.env.PORT || 3000);
